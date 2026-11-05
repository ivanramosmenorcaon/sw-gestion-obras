<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['payment_applications', 'invoice_maturities', 'payment_reversals'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $table->foreignId('project_id')->constrained()->restrictOnDelete();
                if ($name !== 'invoice_maturities') {
                    $table->foreignId('payment_id')->constrained()->restrictOnDelete();
                }
                if ($name !== 'payment_reversals') {
                    $table->foreignId('sales_invoice_id')->nullable()->constrained()->restrictOnDelete();
                    $table->foreignId('purchase_invoice_id')->nullable()->constrained()->restrictOnDelete();
                }
                $table->string('reference', 100);
                $table->text('description');
                $table->decimal('amount', 18, 2);
                $table->date('operation_date');
                $table->string('status', 20)->default('draft');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['payment_reversals', 'invoice_maturities', 'payment_applications'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
