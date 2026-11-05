<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('revises_id')->nullable()->constrained('budgets')->restrictOnDelete();
            $table->foreignId('extra_for_id')->nullable()->constrained('budgets')->restrictOnDelete();
            $table->date('offer_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('terms')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('acceptance_evidence')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('commercial_snapshot')->nullable();
        });
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->foreignId('accepted_budget_id')->nullable()->unique()->constrained('budgets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('accepted_budget_id');
        });
        Schema::table('budgets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revises_id');
            $table->dropConstrainedForeignId('extra_for_id');
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropColumn(['status', 'revision', 'offer_date', 'valid_until', 'terms', 'exclusions', 'acceptance_evidence', 'sent_at', 'accepted_at', 'commercial_snapshot']);
        });
    }
};
