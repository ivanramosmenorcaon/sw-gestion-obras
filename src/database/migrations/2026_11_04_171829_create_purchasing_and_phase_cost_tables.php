<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', fn (Blueprint $table) => $table->decimal('sales_markup_percentage', 9, 4)->default(15));
        Schema::table('products', fn (Blueprint $table) => $table->decimal('sales_markup_percentage', 9, 4)->nullable());
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('purchase_delivery_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100);
            $table->date('received_date');
            $table->char('currency_code', 3)->default('EUR');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->decimal('net_total', 18, 2)->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'reference']);
            $table->index(['status', 'received_date']);
        });
        Schema::create('purchase_delivery_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_delivery_note_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 500);
            $table->unsignedInteger('position')->default(1);
            $table->decimal('quantity', 20, 6);
            $table->decimal('unit_price', 20, 6);
            $table->decimal('discount_percentage', 7, 4)->default(0);
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->decimal('base_unit_factor', 24, 12)->nullable();
            $table->decimal('base_quantity', 20, 6)->nullable();
            $table->decimal('base_unit_cost', 24, 12)->nullable();
            $table->decimal('net_total', 18, 2)->nullable();
            $table->decimal('markup_percentage', 9, 4)->nullable();
            $table->decimal('suggested_sale_price', 20, 6)->nullable();
            $table->json('product_snapshot')->nullable();
            $table->timestamps();
        });
        Schema::create('material_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_delivery_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('work_order_phase_id');
            $table->foreign(['work_order_id', 'work_order_phase_id'], 'consumption_phase_owner_fk')->references(['work_order_id', 'id'])->on('work_order_phases')->restrictOnDelete();
            $table->foreignId('reverses_id')->nullable()->constrained('material_consumptions')->restrictOnDelete();
            $table->date('consumed_date');
            $table->decimal('quantity', 20, 6);
            $table->string('status', 20)->default('draft');
            $table->decimal('unit_cost', 24, 12)->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
            $table->decimal('unit_sale_price', 20, 6)->nullable();
            $table->decimal('sale_total', 18, 2)->nullable();
            $table->decimal('markup_percentage', 9, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['purchase_delivery_line_id', 'status']);
            $table->index(['work_order_phase_id', 'status']);
        });
        Schema::create('labor_cost_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->decimal('hourly_cost', 20, 6);
            $table->char('currency_code', 3)->default('EUR');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'valid_from']);
            $table->index(['contractor_id', 'valid_from']);
        });
        Schema::create('work_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('work_order_phase_id');
            $table->foreign(['work_order_id', 'work_order_phase_id'], 'time_entry_phase_owner_fk')->references(['work_order_id', 'id'])->on('work_order_phases')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->foreignId('labor_cost_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('hours', 16, 6)->nullable();
            $table->decimal('hourly_cost', 20, 6)->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('description');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'started_at']);
            $table->index(['contractor_id', 'started_at']);
            $table->index(['work_order_phase_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['work_time_entries', 'labor_cost_rates', 'material_consumptions', 'purchase_delivery_lines', 'purchase_delivery_notes', 'warehouses'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('sales_markup_percentage'));
        Schema::table('company_settings', fn (Blueprint $table) => $table->dropColumn('sales_markup_percentage'));
    }
};
