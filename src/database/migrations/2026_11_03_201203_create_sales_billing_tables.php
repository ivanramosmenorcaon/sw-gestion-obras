<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_series', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 15);
            $table->string('name', 100);
            $table->unsignedSmallInteger('fiscal_year');
            $table->enum('kind', ['standard', 'rectifying'])->default('standard');
            $table->enum('environment', ['test', 'production'])->default('test');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['code', 'fiscal_year', 'environment']);
        });
        Schema::create('sales_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('invoice_series_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('rectifies_invoice_id')->nullable()->constrained('sales_invoices')->restrictOnDelete();
            $table->enum('status', ['draft', 'issued', 'cancelled'])->default('draft');
            $table->string('invoice_type', 2)->default('F1');
            $table->date('issue_date');
            $table->date('operation_date');
            $table->date('due_date')->nullable();
            $table->char('currency_code', 3)->default('EUR');
            $table->string('number', 60)->nullable();
            $table->unsignedBigInteger('sequence_number')->nullable();
            $table->string('environment', 15)->nullable();
            $table->json('issuer_snapshot')->nullable();
            $table->json('recipient_snapshot')->nullable();
            $table->json('tax_summary')->nullable();
            foreach (['net_total', 'tax_total', 'surcharge_total', 'withholding_total', 'gross_total', 'payable_total'] as $field) {
                $table->decimal($field, 18, 2)->default(0);
            }
            $table->string('description', 500);
            $table->text('fiscal_evidence')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->unique(['invoice_series_id', 'sequence_number']);
            $table->unique(['environment', 'number']);
            $table->index(['customer_id', 'status', 'issue_date']);
        });
        Schema::create('sales_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('rectifies_line_id')->nullable()->constrained('sales_invoice_lines')->restrictOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->string('description', 500);
            $table->string('unit_label', 30)->nullable();
            $table->decimal('quantity', 20, 6);
            $table->decimal('unit_price', 20, 6);
            $table->decimal('discount_percentage', 7, 4)->default(0);
            $table->foreignId('tax_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('withholding_rate_id')->nullable()->constrained('tax_rates')->restrictOnDelete();
            $table->json('fiscal_snapshot')->nullable();
            foreach (['net_amount', 'tax_amount', 'surcharge_amount', 'withholding_amount'] as $field) {
                $table->decimal($field, 18, 2)->default(0);
            }
            $table->timestamps();
        });
        Schema::create('billing_chains', function (Blueprint $table): void {
            $table->id();
            $table->enum('environment', ['test', 'production'])->unique();
            $table->unsignedBigInteger('last_record_id')->nullable();
            $table->timestamps();
        });
        Schema::create('billing_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_invoice_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('billing_chain_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_record_id')->nullable()->constrained('billing_records')->restrictOnDelete();
            $table->char('hash', 64);
            $table->char('previous_hash', 64)->nullable();
            $table->string('generated_at', 35);
            $table->longText('xml');
            $table->json('system_snapshot');
            $table->timestamps();
        });
        Schema::create('billing_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_record_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('retry_after')->nullable();
            $table->string('csv', 100)->nullable();
            $table->string('error_code', 30)->nullable();
            $table->text('error_description')->nullable();
            $table->longText('response_xml')->nullable();
            $table->timestamps();
            $table->index(['status', 'retry_after']);
        });
        Schema::create('billing_submission_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_submission_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('attempt');
            $table->string('status', 30);
            $table->longText('response_xml')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['billing_submission_attempts', 'billing_submissions', 'billing_records', 'billing_chains', 'sales_invoice_lines', 'sales_invoices', 'invoice_series'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
