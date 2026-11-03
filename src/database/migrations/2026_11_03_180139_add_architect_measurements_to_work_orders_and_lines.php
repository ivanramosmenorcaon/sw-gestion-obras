<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->string('measurement_import_hash', 64)->nullable();
            $table->unique(['project_id', 'measurement_import_hash'], 'work_orders_measurement_import_unique');
        });
        Schema::table('work_order_lines', function (Blueprint $table): void {
            $table->string('source_code', 80)->nullable();
            $table->json('measurement_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('work_order_lines', function (Blueprint $table): void {
            $table->dropColumn(['source_code', 'measurement_details']);
        });
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropUnique('work_orders_measurement_import_unique');
            $table->dropColumn('measurement_import_hash');
        });
    }
};
