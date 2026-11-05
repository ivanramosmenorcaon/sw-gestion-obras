<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_delivery_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('target_line_id')->nullable()->constrained('purchase_delivery_lines')->restrictOnDelete();
            $table->string('reference', 100)->unique();
            $table->string('category', 30);
            $table->text('description');
            $table->decimal('quantity', 20, 6);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->date('operation_date');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
        Schema::table('purchase_delivery_notes', function (Blueprint $table): void {
            $table->foreignId('source_transfer_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_delivery_notes', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_transfer_id'));
        Schema::dropIfExists('stock_movements');
    }
};
