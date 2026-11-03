<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->enum('kind', ['vat', 'igic', 'ipsi', 'withholding', 'surcharge', 'other']);
            $table->decimal('percentage', 7, 4);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->text('legal_basis')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['kind', 'is_active']);
        });

        Schema::create('tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->enum('tax_system', ['01', '02', '03', '05'])->default('01');
            $table->char('regime_code', 2)->nullable();
            $table->enum('operation_class', ['S1', 'S2', 'N1', 'N2', 'exempt']);
            $table->char('exemption_code', 2)->nullable();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('surcharge_rate_id')->nullable()->constrained('tax_rates')->restrictOnDelete();
            $table->text('legal_basis')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_profiles');
        Schema::dropIfExists('tax_rates');
    }
};
