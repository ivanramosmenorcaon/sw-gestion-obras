<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_lines', function (Blueprint $table): void {
            $table->json('budget_breakdown')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('work_order_lines', function (Blueprint $table): void {
            $table->dropColumn('budget_breakdown');
        });
    }
};
