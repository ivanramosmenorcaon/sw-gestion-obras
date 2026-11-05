<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_absences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reference', 100);
            $table->text('description');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['employee_id', 'starts_on', 'ends_on']);
        });
        Schema::create('contractor_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('contractor_id')->constrained('parties')->restrictOnDelete();
            $table->string('reference', 100)->unique();
            $table->text('description');
            $table->decimal('amount', 18, 2);
            $table->decimal('retention_percentage', 7, 4)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
        Schema::create('contractor_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('contractor_agreement_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100)->unique();
            $table->text('description');
            $table->decimal('amount', 18, 2);
            $table->date('operation_date');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('parties')->restrictOnDelete();
            $table->string('reference', 100)->unique();
            $table->text('description');
            $table->decimal('amount', 18, 2);
            $table->date('operation_date');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
        Schema::create('project_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_phase_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 100);
            $table->text('description');
            $table->string('category', 30);
            $table->decimal('amount', 18, 2);
            $table->date('operation_date');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
        Schema::create('purchase_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('contractor_agreement_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_delivery_note_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 100);
            $table->text('description');
            $table->date('operation_date');
            $table->date('due_date');
            $table->decimal('net_amount', 18, 2);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->decimal('retention_amount', 18, 2)->default(0);
            $table->decimal('payable_total', 18, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->unique(['supplier_id', 'reference']);
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('direction', 20);
            $table->string('reference', 100);
            $table->string('method', 30);
            $table->decimal('amount', 18, 2);
            $table->date('operation_date');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
        Schema::create('project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100);
            $table->string('category', 30);
            $table->text('description');
            $table->date('operation_date');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
        Schema::create('project_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100);
            $table->string('category', 30);
            $table->string('description', 500);
            $table->string('path', 500);
            $table->unsignedInteger('version')->default(1);
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->unique(['project_id', 'reference', 'version']);
        });
        Schema::create('work_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_line_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100);
            $table->text('description')->nullable();
            $table->decimal('quantity', 20, 6);
            $table->date('operation_date');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
        Schema::create('work_time_reversals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_time_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reference', 100);
            $table->text('description');
            $table->date('operation_date');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
        Schema::table('purchase_delivery_notes', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_delivery_notes', fn (Blueprint $table) => $table->dropConstrainedForeignId('purchase_order_id'));
        foreach (['work_time_reversals', 'work_progress', 'project_documents', 'project_activities', 'payments', 'purchase_invoices', 'project_expenses', 'purchase_orders', 'contractor_certificates', 'contractor_agreements', 'employee_absences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
