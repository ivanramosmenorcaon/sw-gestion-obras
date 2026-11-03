<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('status', ['draft', 'quoted', 'approved', 'in_progress', 'on_hold', 'completed', 'cancelled'])->default('draft');
            $table->char('currency_code', 3)->default('EUR');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->default('ES');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'planned_start_date']);
            $table->timestamps();
        });

        Schema::create('project_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unique(['project_id', 'code']);
            $table->unique(['project_id', 'id']);
            $table->foreign(['project_id', 'parent_id'], 'chapters_parent_same_project_fk')->references(['project_id', 'id'])->on('project_chapters')->restrictOnDelete();
            $table->index(['project_id', 'parent_id', 'position']);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->date('employment_start_date')->nullable();
            $table->date('employment_end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('reference', 80)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->enum('status', ['draft', 'planned', 'in_progress', 'on_hold', 'completed', 'cancelled'])->default('draft');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->text('instructions')->nullable();
            $table->unique(['project_id', 'id']);
            $table->index(['project_id', 'status']);
            $table->index(['status', 'planned_start_date']);
            $table->timestamps();
        });

        Schema::create('work_order_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->enum('status', ['pending', 'in_progress', 'on_hold', 'completed', 'cancelled'])->default('pending');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->unique(['work_order_id', 'code']);
            $table->unique(['work_order_id', 'id']);
            $table->index(['work_order_id', 'position']);
            $table->timestamps();
        });

        Schema::create('work_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('project_chapter_id')->nullable();
            $table->unsignedBigInteger('work_order_phase_id')->nullable();
            $table->unsignedInteger('position');
            $table->enum('type', ['material', 'service', 'labor', 'rental']);
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained('product_units')->restrictOnDelete();
            $table->text('description');
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('unit_label', 100);
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('base_unit_factor', 24, 12)->default(1);
            $table->decimal('planned_quantity', 20, 6)->default(0);
            $table->decimal('estimated_unit_cost', 20, 6)->default(0);
            $table->decimal('estimated_unit_sale_price', 20, 6)->default(0);
            $table->text('notes')->nullable();
            $table->unique(['work_order_id', 'position']);
            $table->foreign(['project_id', 'work_order_id'], 'lines_order_same_project_fk')->references(['project_id', 'id'])->on('work_orders')->restrictOnDelete();
            $table->foreign(['project_id', 'project_chapter_id'], 'lines_chapter_same_project_fk')->references(['project_id', 'id'])->on('project_chapters')->restrictOnDelete();
            $table->foreign(['work_order_id', 'work_order_phase_id'], 'lines_phase_same_order_fk')->references(['work_order_id', 'id'])->on('work_order_phases')->restrictOnDelete();
            $table->foreign(['product_id', 'product_unit_id'], 'lines_presentation_same_product_fk')->references(['product_id', 'id'])->on('product_units')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('work_order_employee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('work_order_phase_id')->nullable();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('role', 120)->nullable();
            $table->enum('status', ['planned', 'active', 'completed', 'cancelled'])->default('planned');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->decimal('planned_hours', 20, 6)->default(0);
            $table->text('notes')->nullable();
            $table->foreign(['work_order_id', 'work_order_phase_id'], 'employee_assignment_phase_fk')->references(['work_order_id', 'id'])->on('work_order_phases')->restrictOnDelete();
            $table->index(['work_order_id', 'status'], 'employee_assignment_order_status_idx');
            $table->index(['employee_id', 'planned_start_date'], 'employee_assignment_schedule_idx');
            $table->timestamps();
        });

        Schema::create('work_order_contractor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('work_order_phase_id')->nullable();
            $table->foreignId('contractor_id')->constrained('parties')->restrictOnDelete();
            $table->text('scope');
            $table->enum('status', ['planned', 'active', 'completed', 'cancelled'])->default('planned');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreign(['work_order_id', 'work_order_phase_id'], 'contractor_assignment_phase_fk')->references(['work_order_id', 'id'])->on('work_order_phases')->restrictOnDelete();
            $table->index(['work_order_id', 'status'], 'contractor_assignment_order_status_idx');
            $table->index(['contractor_id', 'planned_start_date'], 'contractor_assignment_schedule_idx');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_contractor_assignments');
        Schema::dropIfExists('work_order_employee_assignments');
        Schema::dropIfExists('work_order_lines');
        Schema::dropIfExists('work_order_phases');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('project_chapters');
        Schema::dropIfExists('projects');
    }
};
