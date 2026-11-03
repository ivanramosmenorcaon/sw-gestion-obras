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
        Schema::table('business_records', function (Blueprint $table) {
            foreach (['parties', 'products', 'company_settings'] as $name) {
                Schema::table($name, function (Blueprint $table) {
                    $table->foreignId('sales_tax_profile_id')->nullable()->constrained('tax_profiles')->restrictOnDelete();
                    $table->foreignId('purchase_tax_profile_id')->nullable()->constrained('tax_profiles')->restrictOnDelete();
                    $table->foreignId('sales_withholding_rate_id')->nullable()->constrained('tax_rates')->restrictOnDelete();
                    $table->foreignId('purchase_withholding_rate_id')->nullable()->constrained('tax_rates')->restrictOnDelete();
                    $table->text('fiscal_notes')->nullable();
                });
            }

            foreach (['parties', 'company_settings'] as $name) {
                Schema::table($name, function (Blueprint $table) {
                    $table->enum('taxpayer_capacity', ['private_individual', 'business', 'public_body'])->nullable();
                    $table->enum('tax_territory', ['mainland_balearic', 'canary_islands', 'ceuta', 'melilla', 'eu', 'third_country'])->nullable();
                    $table->string('iae_code', 30)->nullable();
                    $table->boolean('withholding_obliged')->nullable();
                    $table->boolean('equivalence_surcharge_applicable')->nullable();
                });
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_records', function (Blueprint $table) {
            foreach (['parties', 'company_settings'] as $name) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropColumn(['taxpayer_capacity', 'tax_territory', 'iae_code', 'withholding_obliged', 'equivalence_surcharge_applicable']);
                });
            }

            foreach (['parties', 'products', 'company_settings'] as $name) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('sales_tax_profile_id');
                    $table->dropConstrainedForeignId('purchase_tax_profile_id');
                    $table->dropConstrainedForeignId('sales_withholding_rate_id');
                    $table->dropConstrainedForeignId('purchase_withholding_rate_id');
                    $table->dropColumn('fiscal_notes');
                });
            }
        });
    }
};
