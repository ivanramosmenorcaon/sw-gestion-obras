<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessMasterDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_seeding_twice_preserves_customizations_without_creating_users(): void
    {
        $this->freezeTime();
        $this->seed(DatabaseSeeder::class);
        DB::table('units')->where('code', 'hour')->update(['name' => 'Hora personalizada']);
        $termId = DB::table('payment_terms')->where('code', 'net_30')->value('id');
        DB::table('payment_term_installments')->where('payment_term_id', $termId)->update(['days_after_reference' => 35]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('units', 11);
        $this->assertDatabaseCount('unit_conversions', 12);
        $this->assertDatabaseCount('payment_terms', 5);
        $this->assertDatabaseCount('payment_term_installments', 7);
        $this->assertDatabaseHas('units', ['code' => 'hour', 'name' => 'Hora personalizada']);
        $this->assertDatabaseHas('payment_term_installments', ['payment_term_id' => $termId, 'days_after_reference' => 35]);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('company_settings', 0);
        $this->assertDatabaseCount('parties', 0);
    }

    public function test_seeded_installments_total_one_hundred_and_keep_work_milestones(): void
    {
        $this->seed(DatabaseSeeder::class);

        $totals = DB::table('payment_term_installments')->selectRaw('payment_term_id, SUM(percentage) AS total')->groupBy('payment_term_id')->get();

        $this->assertCount(5, $totals);
        foreach ($totals as $total) {
            $this->assertSame('100.0000', number_format((float) $total->total, 4, '.', ''));
        }
        $termId = DB::table('payment_terms')->where('code', 'start_30_completion_70')->value('id');
        $this->assertDatabaseHas('payment_term_installments', ['payment_term_id' => $termId, 'sequence' => 1, 'percentage' => 30, 'reference_event' => 'work_start']);
        $this->assertDatabaseHas('payment_term_installments', ['payment_term_id' => $termId, 'sequence' => 2, 'percentage' => 70, 'reference_event' => 'work_completion']);
    }

    public function test_catalog_conversions_do_not_mix_physical_magnitudes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $invalid = DB::table('unit_conversions')
            ->join('units as source', 'source.id', '=', 'unit_conversions.from_unit_id')
            ->join('units as target', 'target.id', '=', 'unit_conversions.to_unit_id')
            ->whereColumn('source.magnitude', '!=', 'target.magnitude')->count();

        $this->assertSame(0, $invalid);
        $this->assertDatabaseHas('unit_conversions', [
            'from_unit_id' => DB::table('units')->where('code', 'kilogram')->value('id'),
            'to_unit_id' => DB::table('units')->where('code', 'gram')->value('id'),
            'factor' => 1000,
        ]);
    }

    public function test_a_party_can_be_both_customer_and_supplier(): void
    {
        $partyId = DB::table('parties')->insertGetId(['type' => 'organization', 'legal_name' => 'Contratista de prueba']);

        DB::table('party_roles')->insert([
            ['party_id' => $partyId, 'role' => 'customer'],
            ['party_id' => $partyId, 'role' => 'supplier'],
        ]);

        $this->assertDatabaseCount('parties', 1);
        $this->assertDatabaseCount('party_roles', 2);
    }

    public function test_a_price_cannot_use_another_products_presentation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unitId = DB::table('units')->where('code', 'unit')->value('id');
        $firstId = DB::table('products')->insertGetId(['reference' => 'A', 'name' => 'Producto A', 'type' => 'material', 'base_unit_id' => $unitId]);
        $secondId = DB::table('products')->insertGetId(['reference' => 'B', 'name' => 'Producto B', 'type' => 'material', 'base_unit_id' => $unitId]);
        $presentationId = DB::table('product_units')->insertGetId(['product_id' => $firstId, 'code' => 'box20', 'name' => 'Caja de 20', 'unit_id' => $unitId, 'base_unit_factor' => '20']);
        $listId = DB::table('price_lists')->insertGetId(['code' => 'general', 'name' => 'General']);
        DB::table('price_list_items')->insert(['price_list_id' => $listId, 'product_id' => $firstId, 'product_unit_id' => $presentationId, 'unit_price' => '12.123456', 'valid_from' => '2026-11-03']);

        $this->assertDatabaseHas('price_list_items', ['product_id' => $firstId, 'product_unit_id' => $presentationId, 'unit_price' => '12.123456']);

        $this->expectException(QueryException::class);

        DB::table('price_list_items')->insert(['price_list_id' => $listId, 'product_id' => $secondId, 'product_unit_id' => $presentationId, 'unit_price' => '12.123456', 'valid_from' => '2026-11-03']);
    }

    public function test_a_unit_used_by_a_product_cannot_be_deleted(): void
    {
        $unitId = DB::table('units')->insertGetId(['code' => 'unit', 'name' => 'Unidad', 'symbol' => 'ud', 'magnitude' => 'count']);
        DB::table('products')->insert(['reference' => 'A', 'name' => 'Producto A', 'type' => 'material', 'base_unit_id' => $unitId]);

        $this->expectException(QueryException::class);

        DB::table('units')->where('id', $unitId)->delete();
    }

    public function test_only_one_company_settings_record_is_allowed(): void
    {
        $company = ['legal_name' => 'Empresa de prueba', 'tax_identification_number' => 'TEST', 'address_line_1' => 'Dirección de prueba', 'city' => 'Madrid'];
        DB::table('company_settings')->insert($company);

        $this->expectException(QueryException::class);

        DB::table('company_settings')->insert($company);
    }
}
