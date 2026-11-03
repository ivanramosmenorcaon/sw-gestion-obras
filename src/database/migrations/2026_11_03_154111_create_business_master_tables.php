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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['company'])->default('company')->unique();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('tax_identification_type', 30)->default('nif');
            $table->string('tax_identification_number', 40);
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 120);
            $table->string('province', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->default('ES');
            $table->char('currency_code', 3)->default('EUR');
            $table->string('timezone', 64)->default('Europe/Madrid');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['individual', 'organization']);
            $table->string('legal_name')->index();
            $table->string('trade_name')->nullable();
            $table->char('tax_country_code', 2)->default('ES');
            $table->string('tax_identification_type', 30)->default('nif');
            $table->string('tax_identification_number', 40)->nullable();
            $table->string('locale', 10)->default('es');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique(['tax_country_code', 'tax_identification_type', 'tax_identification_number'], 'parties_tax_identity_unique');
            $table->timestamps();
        });

        Schema::create('party_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['customer', 'supplier', 'subcontractor']);
            $table->unique(['party_id', 'role']);
            $table->index(['role', 'party_id']);
            $table->timestamps();
        });

        Schema::create('party_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('label', 100);
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 120);
            $table->string('province', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->default('ES');
            $table->boolean('is_fiscal')->default(false);
            $table->boolean('is_delivery')->default(false);
            $table->boolean('is_active')->default(true);
            $table->index(['party_id', 'is_active']);
            $table->timestamps();
        });

        Schema::create('party_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('position', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->index(['party_id', 'is_active']);
            $table->timestamps();
        });

        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_term_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_term_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('percentage', 7, 4);
            $table->unsignedSmallInteger('days_after_reference')->default(0);
            $table->enum('reference_event', ['invoice_date', 'work_start', 'work_completion'])->default('invoice_date');
            $table->unique(['payment_term_id', 'sequence']);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('symbol', 20);
            $table->enum('magnitude', ['count', 'time', 'mass', 'length', 'area', 'volume']);
            $table->unsignedTinyInteger('display_precision')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('to_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('factor', 24, 12);
            $table->unique(['from_unit_id', 'to_unit_id']);
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->foreignId('product_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('type', ['material', 'service', 'labor', 'rental']);
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->boolean('tracks_stock')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('base_unit_factor', 24, 12);
            $table->boolean('available_for_purchase')->default(true);
            $table->boolean('available_for_sale')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unique(['product_id', 'code']);
            $table->unique(['product_id', 'id']);
            $table->timestamps();
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_unit_id');
            $table->string('supplier_reference', 100);
            $table->decimal('minimum_order_quantity', 20, 6)->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreign(['product_id', 'product_unit_id'])->references(['product_id', 'id'])->on('product_units')->restrictOnDelete();
            $table->unique(['supplier_id', 'supplier_reference']);
            $table->timestamps();
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->char('currency_code', 3)->default('EUR');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_unit_id');
            $table->decimal('minimum_quantity', 20, 6)->default(1);
            $table->decimal('unit_price', 20, 6);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->foreign(['product_id', 'product_unit_id'])->references(['product_id', 'id'])->on('product_units')->restrictOnDelete();
            $table->unique(['price_list_id', 'product_unit_id', 'minimum_quantity', 'valid_from'], 'price_items_break_date_unique');
            $table->timestamps();
        });

        Schema::create('party_customer_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer_reference', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('party_supplier_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('account_reference', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('party_supplier_settings');
        Schema::dropIfExists('party_customer_settings');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('units');
        Schema::dropIfExists('payment_term_installments');
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('party_contacts');
        Schema::dropIfExists('party_addresses');
        Schema::dropIfExists('party_roles');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('company_settings');
    }
};
