<?php

namespace Tests\Feature;

use App\Filament\Resources\Parties\Pages\EditParty;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\TaxProfiles\Pages\CreateTaxProfile;
use App\Filament\Resources\TaxProfiles\Pages\EditTaxProfile;
use App\Filament\Resources\TaxProfiles\Pages\ListTaxProfiles;
use App\Filament\Resources\TaxRates\Pages\CreateTaxRate;
use App\Filament\Resources\TaxRates\Pages\ListTaxRates;
use App\Models\CompanySetting;
use App\Models\Party;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\FiscalCatalog;
use App\Support\FiscalIdentity;
use Database\Seeders\FiscalCatalogSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FiscalConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_fiscal_catalog_can_be_reseeded_without_overwriting_configuration(): void
    {
        $this->freezeTime();
        $this->seed(FiscalCatalogSeeder::class);
        TaxRate::query()->where('code', 'VAT_21_2026')->firstOrFail()->update(['name' => 'IVA personalizado']);

        $this->seed(FiscalCatalogSeeder::class);

        $this->assertDatabaseCount('tax_rates', 11);
        $this->assertDatabaseCount('tax_profiles', 16);
        $this->assertDatabaseHas('tax_rates', ['code' => 'VAT_21_2026', 'name' => 'IVA personalizado']);
        foreach (TaxProfile::all() as $profile) {
            $profile->save();
        }
        $this->assertDatabaseCount('parties', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('company_settings', 0);
    }

    #[DataProvider('validIdentities')]
    public function test_recipient_identity_uses_the_correct_nif_or_idotro_branch(string $type, string $country, string $number, string $branch): void
    {
        $party = Party::factory()->create(['tax_identification_type' => $type, 'tax_country_code' => $country, 'tax_identification_number' => $number]);

        $identity = FiscalIdentity::recipient($party);

        $this->assertArrayHasKey($branch, $identity);
        $this->assertArrayNotHasKey($branch === 'NIF' ? 'IDOtro' : 'NIF', $identity);
        if ($branch === 'IDOtro') {
            $this->assertSame(['CodigoPais' => $country, 'IDType' => $type, 'ID' => $number], $identity['IDOtro']);
        } else {
            $this->assertSame($number, $identity['NIF']);
        }
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function validIdentities(): array
    {
        return [
            'NIF español' => ['nif', 'ES', 'B12345674', 'NIF'],
            'NIE español' => ['nif', 'ES', 'X1234567L', 'NIF'],
            'IVA intracomunitario' => ['02', 'FR', 'FR40303265045', 'IDOtro'],
            'pasaporte español' => ['03', 'ES', 'ABC123456', 'IDOtro'],
            'documento extranjero' => ['04', 'US', 'ABC123456', 'IDOtro'],
            'certificado residencia' => ['05', 'US', 'CERT123', 'IDOtro'],
            'otro documento' => ['06', 'US', 'DOC123', 'IDOtro'],
            'no censado destinatario' => ['07', 'ES', 'B12345674', 'IDOtro'],
        ];
    }

    public function test_a_contact_without_tax_number_can_be_saved_but_is_not_ready_for_a_recipient_record(): void
    {
        $party = Party::factory()->create();
        $this->assertModelExists($party);

        $this->expectException(ValidationException::class);

        FiscalIdentity::recipient($party);
    }

    public function test_foreign_identity_type_is_rejected_for_a_spanish_identifier_country(): void
    {
        $this->expectException(ValidationException::class);

        Party::factory()->create(['tax_identification_type' => '04', 'tax_country_code' => 'ES', 'tax_identification_number' => 'ABC123']);
    }

    public function test_country_codes_are_validated_against_the_official_catalog(): void
    {
        $this->assertSame('España', FiscalCatalog::countries()['ES']);
        $this->expectException(ValidationException::class);

        Party::factory()->create(['tax_country_code' => 'ZZ']);
    }

    #[DataProvider('inconsistentProfiles')]
    public function test_rejects_inconsistent_tax_profiles(string $scenario): void
    {
        $this->seed(FiscalCatalogSeeder::class);
        $vat21 = TaxRate::query()->where('code', 'VAT_21_2026')->firstOrFail();
        $vat0 = TaxRate::query()->where('code', 'VAT_0_2026')->firstOrFail();
        $surcharge = TaxRate::query()->where('code', 'RE_0_5_2026')->firstOrFail();
        $irpf = TaxRate::query()->where('code', 'IRPF_15_2026')->firstOrFail();
        $data = ['operation_class' => 'S1', 'tax_rate_id' => $vat21->id];
        $changes = match ($scenario) {
            'exempt_with_rate' => ['operation_class' => 'exempt', 'exemption_code' => 'E1', 'legal_basis' => 'Artículo 20 LIVA'],
            'reverse_nonzero' => ['operation_class' => 'S2', 'legal_basis' => 'Artículo 84 LIVA'],
            'nonsubject_with_rate' => ['operation_class' => 'N1', 'legal_basis' => 'Artículo 7 LIVA'],
            'retention_as_vat' => ['tax_rate_id' => $irpf->id],
            'wrong_surcharge' => ['surcharge_rate_id' => $surcharge->id],
            'exemption_outside_igic' => ['operation_class' => 'exempt', 'exemption_code' => 'E7', 'tax_rate_id' => null, 'legal_basis' => 'No aplicable al IVA'],
            'export_taxable' => ['regime_code' => '02'],
            'wrong_localization' => ['regime_code' => '08'],
            'reverse_surcharge' => ['operation_class' => 'S2', 'tax_rate_id' => $vat0->id, 'surcharge_rate_id' => $surcharge->id, 'legal_basis' => 'Artículo 84 LIVA'],
        };
        $this->expectException(ValidationException::class);

        TaxProfile::factory()->create(array_replace($data, $changes));
    }

    /** @return array<string, array{string}> */
    public static function inconsistentProfiles(): array
    {
        return [
            'exenta con cuota' => ['exempt_with_rate'],
            'inversión con IVA' => ['reverse_nonzero'],
            'no sujeta con IVA' => ['nonsubject_with_rate'],
            'IRPF como IVA' => ['retention_as_vat'],
            'recargo incompatible' => ['wrong_surcharge'],
            'E7 no es exención IVA' => ['exemption_outside_igic'],
            'exportación no exenta' => ['export_taxable'],
            'régimen 08 sin N2' => ['wrong_localization'],
            'inversión con recargo' => ['reverse_surcharge'],
        ];
    }

    public function test_igic_has_its_own_exemptions_and_regimes(): void
    {
        $profile = TaxProfile::factory()->create(['tax_system' => '03', 'regime_code' => '01', 'operation_class' => 'exempt', 'exemption_code' => 'E7', 'tax_rate_id' => null, 'legal_basis' => 'Artículo 90 del Decreto Legislativo 1/2025']);

        $this->assertModelExists($profile);
        $this->assertArrayHasKey('21', FiscalCatalog::regimes('03'));
        $this->assertArrayNotHasKey('21', FiscalCatalog::regimes('01'));
    }

    public function test_an_indirect_tax_cannot_be_assigned_as_a_withholding_default(): void
    {
        $vat = TaxRate::factory()->create();
        $this->expectException(ValidationException::class);

        Product::factory()->create(['sales_withholding_rate_id' => $vat->id]);
    }

    public function test_defaults_remain_unassigned_until_an_explicit_choice_is_made(): void
    {
        $party = Party::factory()->create();
        $product = Product::factory()->create();
        $company = CompanySetting::factory()->create();

        foreach ([$party, $product, $company] as $record) {
            $this->assertNull($record->fresh()->sales_tax_profile_id);
            $this->assertNull($record->fresh()->purchase_withholding_rate_id);
        }
    }

    public function test_both_fiscal_resources_can_create_and_list_records(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateTaxRate::class)->fillForm(['code' => 'IVA21', 'name' => 'IVA 21 %', 'kind' => 'vat', 'percentage' => '21.00', 'valid_from' => '2026-01-01'])->call('create')->assertHasNoFormErrors()->assertNotified();
        $rate = TaxRate::query()->firstOrFail();
        Livewire::test(CreateTaxProfile::class)->fillForm(['code' => 'GENERAL', 'name' => 'General', 'tax_system' => '01', 'regime_code' => '01', 'operation_class' => 'S1', 'tax_rate_id' => $rate->id])->call('create')->assertHasNoFormErrors()->assertNotified();

        Livewire::test(ListTaxRates::class)->assertCanSeeTableRecords([$rate]);
        Livewire::test(ListTaxProfiles::class)->assertCanSeeTableRecords([TaxProfile::query()->firstOrFail()]);
    }

    public function test_product_tax_and_withholding_defaults_can_be_saved_independently(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seed(FiscalCatalogSeeder::class);
        $product = Product::factory()->create();
        $sale = TaxProfile::query()->where('code', 'IVA_21')->firstOrFail();
        $purchase = TaxProfile::query()->where('code', 'IVA_10')->firstOrFail();
        $irpf = TaxRate::query()->where('code', 'IRPF_15_2026')->firstOrFail();

        Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['sales_tax_profile_id' => $sale->id, 'purchase_tax_profile_id' => $purchase->id, 'sales_withholding_rate_id' => $irpf->id])->call('save')->assertHasNoFormErrors()->assertNotified();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'sales_tax_profile_id' => $sale->id, 'purchase_tax_profile_id' => $purchase->id, 'sales_withholding_rate_id' => $irpf->id, 'purchase_withholding_rate_id' => null]);
    }

    public function test_contact_fiscal_configuration_can_be_saved_from_filament(): void
    {
        $this->actingAs(User::factory()->create());
        $party = Party::factory()->create();

        Livewire::test(EditParty::class, ['record' => $party->id])->fillForm(['taxpayer_capacity' => 'business', 'tax_territory' => 'mainland_balearic', 'withholding_obliged' => '1', 'equivalence_surcharge_applicable' => '0', 'iae_code' => '504.1'])->call('save')->assertHasNoFormErrors()->assertNotified();

        $this->assertDatabaseHas('parties', ['id' => $party->id, 'taxpayer_capacity' => 'business', 'withholding_obliged' => true, 'equivalence_surcharge_applicable' => false, 'iae_code' => '504.1']);
        Livewire::test(EditParty::class, ['record' => $party->id])->assertFormSet(['withholding_obliged' => true, 'equivalence_surcharge_applicable' => false]);
    }

    public function test_changing_a_profile_to_exempt_clears_its_old_tax_rate(): void
    {
        $this->actingAs(User::factory()->create());
        $profile = TaxProfile::factory()->create();

        Livewire::test(EditTaxProfile::class, ['record' => $profile->id])->fillForm(['operation_class' => 'exempt', 'exemption_code' => 'E1', 'tax_rate_id' => null, 'legal_basis' => 'Artículo 20 LIVA'])->call('save')->assertHasNoFormErrors()->assertNotified();

        $this->assertDatabaseHas('tax_profiles', ['id' => $profile->id, 'operation_class' => 'exempt', 'exemption_code' => 'E1', 'tax_rate_id' => null]);
    }

    public function test_local_identity_check_does_not_claim_to_have_consulted_aeat(): void
    {
        $this->actingAs(User::factory()->create());
        $party = Party::factory()->create(['tax_identification_number' => 'B12345674']);

        Livewire::test(EditParty::class, ['record' => $party->id])->callAction('checkFiscalIdentity')->assertNotified(__('fiscal.messages.identity_valid'));
    }
}
