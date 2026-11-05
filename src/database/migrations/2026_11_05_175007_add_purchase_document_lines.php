<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_order_lines' => 'purchase_order_id', 'purchase_invoice_lines' => 'purchase_invoice_id'] as $table => $owner) {
            Schema::create($table, function (Blueprint $table) use ($owner): void {
                $table->id();
                $table->foreignId($owner)->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
                if ($owner === 'purchase_invoice_id') {
                    $table->foreignId('purchase_order_line_id')->nullable()->constrained()->restrictOnDelete();
                    $table->foreignId('purchase_delivery_line_id')->nullable()->constrained()->restrictOnDelete();
                }
                $table->unsignedInteger('position')->default(1);
                $table->string('description', 1000);
                $table->string('unit_label', 100);
                $table->decimal('quantity', 20, 6);
                $table->decimal('unit_price', 20, 6);
                $table->decimal('discount_percentage', 7, 4)->default(0);
                $table->decimal('tax_percentage', 7, 4)->default(0);
                $table->decimal('withholding_percentage', 7, 4)->default(0);
                $table->decimal('retention_percentage', 7, 4)->default(0);
                $table->decimal('net_amount', 18, 2)->default(0);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('withholding_amount', 18, 2)->default(0);
                $table->decimal('retention_amount', 18, 2)->default(0);
                $table->timestamps();
                $table->index([$owner, 'position']);
            });
        }
        Schema::table('purchase_delivery_lines', function (Blueprint $table): void {
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_delivery_lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('purchase_order_line_id'));
        Schema::dropIfExists('purchase_invoice_lines');
        Schema::dropIfExists('purchase_order_lines');
    }
};
