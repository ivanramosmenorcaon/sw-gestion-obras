<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->string('measurement_source_path')->nullable();
            $table->string('measurement_source_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn(['measurement_source_path', 'measurement_source_name']);
        });
    }
};
