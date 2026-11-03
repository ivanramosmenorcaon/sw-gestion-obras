<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Filament\Resources\SalesInvoices\Pages\ViewSalesInvoice;
use App\Filament\Resources\SalesInvoices\RelationManagers\LinesRelationManager;
use App\Filament\Resources\WorkOrders\Pages\EditWorkOrder;
use App\Models\BillingRecord;
use App\Models\CompanySetting;
use App\Models\InvoiceSeries;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\Project;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\TaxProfile;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Models\WorkOrderPhase;
use App\Support\InvoiceCalculator;
use App\Support\SalesBilling;
use App\Support\VerifactuClient;
use App\Support\VerifactuXml;
use Database\Seeders\BillingSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SalesBillingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-03 12:00:00', 'Europe/Madrid'));
        config(['billing.producer_name' => 'Productor de pruebas', 'billing.producer_nif' => 'B12345674', 'billing.installation' => 'test-installation']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        Http::preventStrayRequests();
    }

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    private function invoice(): SalesInvoice
    {
        CompanySetting::factory()->create(['tax_identification_number' => 'B12345674', 'postal_code' => '07730']);
        $customer = Party::factory()->customer()->create(['tax_identification_number' => '12345678Z', 'taxpayer_capacity' => 'business', 'withholding_obliged' => true]);
        PartyAddress::factory()->create(['party_id' => $customer->id, 'is_fiscal' => true, 'postal_code' => '07730']);
        $invoice = SalesInvoice::factory()->create(['customer_id' => $customer->id]);
        SalesInvoiceLine::factory()->create(['sales_invoice_id' => $invoice->id]);
        return $invoice;
    }

    public function test_issuance_freezes_fiscal_data_creates_valid_xml_and_keeps_irpf_out_of_registry_total(): void
    {
        $invoice = $this->invoice();
        CompanySetting::first()->update(['tax_identification_number' => '12345678Z']);
        $rate = TaxRate::factory()->create(['kind' => 'withholding', 'percentage' => '15']);
        $invoice->lines()->first()->update(['withholding_rate_id' => $rate->id]);

        $issued = app(SalesBilling::class)->issue($invoice);

        $this->assertSame('100.00', $issued->net_total);
        $this->assertSame('21.00', $issued->tax_total);
        $this->assertSame('121.00', $issued->gross_total);
        $this->assertSame('106.00', $issued->payable_total);
        $this->assertSame(2, $invoice->invoiceSeries->fresh()->next_number);
        $this->assertDatabaseCount('billing_records', 1);
        $this->assertDatabaseHas('billing_submissions', ['status' => 'pending']);
        app(VerifactuXml::class)->validate($issued->billingRecord->xml);
        $this->assertStringContainsString('<sf:ImporteTotal>121.00</sf:ImporteTotal>', $issued->billingRecord->xml);
        $this->assertStringNotContainsString('Retencion', $issued->billingRecord->xml);
        $name = $issued->recipient_snapshot['identity']['NombreRazon'];
        $issued->customer->update(['legal_name' => 'Nombre cambiado']);
        $this->assertSame($name, $issued->fresh()->recipient_snapshot['identity']['NombreRazon']);
        Http::assertNothingSent();
    }

    public function test_official_aeat_hash_example_matches_published_vector(): void
    {
        $this->assertSame('3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60', VerifactuXml::hash('89890001K', '12345678/G33', '01-01-2024', 'F1', '12.35', '123.45', '', '2024-01-01T19:20:30+01:00'));
    }

    public function test_issued_invoice_cannot_be_overwritten_from_a_stale_editor(): void
    {
        $stale = $this->invoice();
        app(SalesBilling::class)->issue($stale);
        $this->expectException(ValidationException::class);
        $stale->update(['description' => 'Cambio tardío']);
    }

    public function test_lines_of_issued_invoices_cannot_be_changed_or_removed(): void
    {
        $invoice = app(SalesBilling::class)->issue($this->invoice());
        foreach (['save', 'delete'] as $method) {
            try {
                $line = $invoice->lines()->first();
                $line->description = 'Cambio'; $line->$method();
                $this->fail('La línea emitida se ha modificado.');
            } catch (ValidationException $exception) { $this->assertArrayHasKey('status', $exception->errors()); }
        }
        $this->assertDatabaseHas('sales_invoice_lines', ['description' => 'Instalación']);
    }

    public function test_all_series_share_one_chain_and_numbers_are_allocated_only_on_issue(): void
    {
        $first = $this->invoice();
        $second = SalesInvoice::factory()->create(['customer_id' => $first->customer_id]);
        SalesInvoiceLine::factory()->create(['sales_invoice_id' => $second->id]);
        $this->assertNull($second->number);
        app(SalesBilling::class)->issue($first);
        $issued = app(SalesBilling::class)->issue($second);
        $this->assertSame(BillingRecord::first()->hash, $issued->billingRecord->previous_hash);
        $this->assertSame(BillingRecord::first()->id, $issued->billingRecord->previous_record_id);
        $this->assertStringContainsString('RegistroAnterior', $issued->billingRecord->xml);
    }

    public function test_failed_issuance_does_not_consume_a_number_or_create_an_audit_record(): void
    {
        $invoice = $this->invoice();
        $invoice->customer->partyAddresses()->update(['is_fiscal' => false]);
        try { app(SalesBilling::class)->issue($invoice); $this->fail('Se ha emitido sin domicilio fiscal.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('customer_id', $exception->errors()); }
        $this->assertSame(1, $invoice->invoiceSeries->fresh()->next_number);
        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertDatabaseCount('billing_records', 0);
    }

    public function test_production_emission_is_blocked_before_allocating_a_number(): void
    {
        $invoice = $this->invoice();
        $invoice->invoiceSeries->update(['environment' => 'production']);
        try { app(SalesBilling::class)->issue($invoice); $this->fail('Producción no debe estar habilitada.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('invoice_series_id', $exception->errors()); }
        $this->assertSame(1, $invoice->invoiceSeries->fresh()->next_number);
        $this->assertDatabaseCount('billing_records', 0);
    }

    public function test_phase_drafts_reserve_quantities_and_cancellation_releases_them(): void
    {
        $invoice = $this->invoice();
        $company = CompanySetting::first(); $company->update(['sales_tax_profile_id' => $invoice->lines()->first()->tax_profile_id]);
        $order = WorkOrder::factory()->create(['project_id' => Project::factory()->create(['customer_id' => $invoice->customer_id])->id]);
        $phase = WorkOrderPhase::factory()->create(['work_order_id' => $order->id]);
        $source = WorkOrderLine::factory()->create(['work_order_id' => $order->id, 'work_order_phase_id' => $phase->id, 'planned_quantity' => '10', 'estimated_unit_sale_price' => '20']);
        $billing = app(SalesBilling::class);

        $draft = $billing->fromWorkOrder($order, $invoice->invoice_series_id, [$phase->id], '50');
        $this->assertSame('5.000000', $draft->lines()->first()->quantity);
        $this->assertSame('5.000000', $billing->remaining($source->id));
        $second = $billing->fromWorkOrder($order, $invoice->invoice_series_id, [$phase->id]);
        $this->assertSame('0.000000', $billing->remaining($source->id));
        try { $second->lines()->first()->update(['quantity' => '6']); $this->fail('Se permite sobrefacturación.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('quantity', $exception->errors()); }
        $billing->cancel($draft);
        $this->assertSame('5.000000', $billing->remaining($source->id));
        $this->assertNull($draft->fresh()->number);
        $this->assertFalse(\App\Support\BusinessRecordValidator::canDelete($order));
    }

    public function test_a_phase_from_another_order_is_rejected(): void
    {
        $order = WorkOrder::factory()->create(); $phase = WorkOrderPhase::factory()->create();
        $this->expectException(ValidationException::class);
        app(SalesBilling::class)->fromWorkOrder($order, InvoiceSeries::factory()->create()->id, [$phase->id]);
    }

    public function test_credits_keep_original_tax_rates_and_cannot_exceed_original_quantities(): void
    {
        $original = app(SalesBilling::class)->issue($this->invoice());
        $series = InvoiceSeries::factory()->rectifying()->create();
        $credit = app(SalesBilling::class)->credit($original, $series->id, 'R4', 'Descuento posterior');
        $credit->lines()->first()->update(['quantity' => '-0.5']);
        $original->lines()->first()->taxProfile->taxRate->update(['percentage' => '10']);
        $issued = app(SalesBilling::class)->issue($credit);
        $this->assertSame('-60.50', $issued->gross_total);
        $this->assertSame('-10.50', $issued->tax_total);
        $this->assertStringContainsString('<sf:TipoRectificativa>I</sf:TipoRectificativa>', $issued->billingRecord->xml);
        $second = app(SalesBilling::class)->credit($original, $series->id, 'R4', 'Resto');
        $this->assertSame('-0.500000', $second->lines()->first()->quantity);
        $this->expectException(ValidationException::class);
        $second->lines()->first()->update(['quantity' => '-1']);
    }

    public function test_s2_has_zero_quota_and_requires_documented_evidence(): void
    {
        $invoice = $this->invoice();
        $profile = TaxProfile::factory()->create(['operation_class' => 'S2', 'tax_rate_id' => TaxRate::factory()->create(['percentage' => '0'])->id, 'legal_basis' => 'Artículo 84.Uno.2.f LIVA', 'requires_evidence' => true]);
        $invoice->lines()->first()->update(['tax_profile_id' => $profile->id]);
        try { app(SalesBilling::class)->issue($invoice); $this->fail('Se ha aplicado ISP sin justificación.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('fiscal_evidence', $exception->errors()); }
        $invoice->update(['fiscal_evidence' => 'Comunicación del promotor y contrato de ejecución de obra.']);
        $issued = app(SalesBilling::class)->issue($invoice);
        $this->assertSame('100.00', $issued->gross_total);
        $this->assertStringContainsString('<sf:CalificacionOperacion>S2</sf:CalificacionOperacion>', $issued->billingRecord->xml);
        $this->assertStringContainsString('<sf:CuotaRepercutida>0.00</sf:CuotaRepercutida>', $issued->billingRecord->xml);
    }

    public function test_group_rounding_uses_decimal_arithmetic_and_allocates_remainders(): void
    {
        $invoice = $this->invoice(); $line = $invoice->lines()->first(); $line->update(['unit_price' => '0.03']);
        SalesInvoiceLine::factory()->create(['sales_invoice_id' => $invoice->id, 'tax_profile_id' => $line->tax_profile_id, 'unit_price' => '0.03']);
        $calculation = app(InvoiceCalculator::class)->calculate($invoice);
        $this->assertSame('0.06', $calculation['totals']['net_total']);
        $this->assertSame('0.01', $calculation['totals']['tax_total']);
        $this->assertSame('0.07', $calculation['totals']['gross_total']);
    }

    public function test_invoice_editor_relation_manager_and_issue_action_are_wired(): void
    {
        $this->actingAs(User::factory()->create());
        $invoice = $this->invoice();
        Livewire::test(ListSalesInvoices::class)->assertCanSeeTableRecords([$invoice]);
        Livewire::test(LinesRelationManager::class, ['ownerRecord' => $invoice, 'pageClass' => EditSalesInvoice::class])->assertCanSeeTableRecords($invoice->lines);
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])->callAction('issue')->assertHasNoActionErrors()->assertRedirect();
        $this->assertSame('issued', $invoice->fresh()->status);
        Livewire::test(ViewSalesInvoice::class, ['record' => $invoice->id])->assertSuccessful();
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])->assertForbidden();
    }

    public function test_print_requires_auth_and_uses_frozen_data_with_test_warning(): void
    {
        $invoice = app(SalesBilling::class)->issue($this->invoice());
        $url = route('sales-invoices.print', $invoice);
        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create());
        $oldName = $invoice->recipient_snapshot['identity']['NombreRazon'];
        $invoice->customer->update(['legal_name' => '<script>nuevo nombre</script>']);
        $this->get(route('sales-invoices.xml', $invoice))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertContent($invoice->billingRecord->xml);
        $this->get($url)->assertOk()->assertSee('SIN VALIDEZ FISCAL')->assertSee($oldName)->assertDontSee('nuevo nombre')->assertSee('prewww2.aeat.es')->assertSee('data:image/svg+xml');

    }

    public function test_seeders_do_not_create_live_series_or_reset_counters(): void
    {
        $this->seed(BillingSeeder::class);
        $series = InvoiceSeries::where('kind', 'standard')->first(); $series->next_number = 27; $series->save();
        $this->seed(BillingSeeder::class);
        $this->assertSame(27, $series->fresh()->next_number);
        $this->assertDatabaseCount('invoice_series', 2);
        $this->assertDatabaseMissing('invoice_series', ['environment' => 'production']);
    }

    private function responseXml(SalesInvoice $invoice, string $state = 'Correcto', string $error = ''): string
    {
        $responseNs = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd';
        $number = htmlspecialchars($invoice->number, ENT_XML1);
        $date = $invoice->issue_date->format('d-m-Y');
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:r="'.$responseNs.'" xmlns:sf="'.VerifactuXml::INFORMATION_NS.'"><soap:Body><r:RespuestaRegFactuSistemaFacturacion><r:CSV>CSVPRUEBAS</r:CSV><r:Cabecera><sf:ObligadoEmision><sf:NombreRazon>Emisor de pruebas</sf:NombreRazon><sf:NIF>B12345674</sf:NIF></sf:ObligadoEmision></r:Cabecera><r:TiempoEsperaEnvio>60</r:TiempoEsperaEnvio><r:EstadoEnvio>'.($state === 'Incorrecto' ? 'Incorrecto' : 'Correcto').'</r:EstadoEnvio><r:RespuestaLinea><r:IDFactura><sf:IDEmisorFactura>B12345674</sf:IDEmisorFactura><sf:NumSerieFactura>'.$number.'</sf:NumSerieFactura><sf:FechaExpedicionFactura>'.$date.'</sf:FechaExpedicionFactura></r:IDFactura><r:Operacion><sf:TipoOperacion>Alta</sf:TipoOperacion></r:Operacion><r:EstadoRegistro>'.$state.'</r:EstadoRegistro>'.$error.'</r:RespuestaLinea></r:RespuestaRegFactuSistemaFacturacion></soap:Body></soap:Envelope>';
    }

    public function test_aeat_responses_are_validated_and_must_match_the_sent_invoice(): void
    {
        $invoice = app(SalesBilling::class)->issue($this->invoice());
        $client = app(VerifactuClient::class);
        $result = $client->parse($this->responseXml($invoice), $invoice->billingRecord->submission);
        $this->assertSame('accepted', $result['status']);
        $this->assertSame('CSVPRUEBAS', $result['csv']);
        $result = $client->parse($this->responseXml($invoice, 'AceptadoConErrores', '<r:CodigoErrorRegistro>2000</r:CodigoErrorRegistro><r:DescripcionErrorRegistro>Revisar</r:DescripcionErrorRegistro>'), $invoice->billingRecord->submission);
        $this->assertSame('accepted_with_errors', $result['status']);
        $this->expectException(\RuntimeException::class);
        $client->parse(str_replace($invoice->number, 'OTRA/00001', $this->responseXml($invoice)), $invoice->billingRecord->submission);
    }

    public function test_transport_retry_reuses_original_payload_and_preserves_attempt_history(): void
    {
        $invoice = app(SalesBilling::class)->issue($this->invoice());
        $certificate = tempnam(sys_get_temp_dir(), 'billing-cert-');
        config(['billing.send_enabled' => true, 'billing.certificate_path' => $certificate, 'billing.private_key_path' => $certificate]);
        try {
            Http::fakeSequence()->push('Servicio temporalmente no disponible', 503)->push($this->responseXml($invoice));
            $client = app(VerifactuClient::class);
            $submission = $invoice->billingRecord->submission;
            $client->send($submission);
            $this->assertSame('transport_error', $submission->fresh()->status);
            $client->send($submission);
            Http::assertSentCount(1);
            $this->travel(61)->seconds();
            $client->send($submission);
            $this->assertSame('accepted', $submission->fresh()->status);
            $this->assertSame(2, $submission->fresh()->attempts);
            $this->assertDatabaseCount('billing_submission_attempts', 2);
            $this->assertDatabaseCount('billing_records', 1);
            $sent = Http::recorded();
            $this->assertSame($sent[0][0]->body(), $sent[1][0]->body());
            $this->assertStringContainsString($invoice->billingRecord->hash, $sent[1][0]->body());
            $client->send($submission);
            Http::assertSentCount(2);
        } finally { unlink($certificate); }
    }

    public function test_missing_certificate_never_claims_or_sends_a_record(): void
    {
        $invoice = app(SalesBilling::class)->issue($this->invoice());
        config(['billing.send_enabled' => true, 'billing.certificate_path' => '/missing.pem', 'billing.private_key_path' => '/missing-key.pem']);
        try { app(VerifactuClient::class)->send($invoice->billingRecord->submission); $this->fail('Debe exigir certificado.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('status', $exception->errors()); }
        $this->assertSame('pending', $invoice->billingRecord->submission->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_customer_can_be_created_and_selected_inside_an_invoice(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(CreateSalesInvoice::class);
        $page->callAction(\Filament\Actions\Testing\TestAction::make('createOption')->schemaComponent('customer_id'), ['type' => 'organization', 'legal_name' => 'Cliente facturación'])->assertHasNoFormErrors();
        $customer = Party::where('legal_name', 'Cliente facturación')->firstOrFail();
        $this->assertDatabaseHas('party_roles', ['party_id' => $customer->id, 'role' => 'customer']);
        $page->assertFormSet(['customer_id' => $customer->id]);
    }

    public function test_inline_retention_creation_preserves_tax_family(): void
    {
        $this->actingAs(User::factory()->create());
        $invoice = $this->invoice();
        $page = Livewire::test(LinesRelationManager::class, ['ownerRecord' => $invoice, 'pageClass' => EditSalesInvoice::class])->mountAction(\Filament\Actions\Testing\TestAction::make('create')->table());
        $page->callAction(\Filament\Actions\Testing\TestAction::make('createOption')->schemaComponent('withholding_rate_id'), ['code' => 'IRPF_INLINE', 'name' => 'IRPF 15', 'percentage' => '15', 'valid_from' => '2026-01-01'])->assertHasNoFormErrors();
        $this->assertDatabaseHas('tax_rates', ['code' => 'IRPF_INLINE', 'kind' => 'withholding']);
    }

    public function test_work_order_action_creates_a_phase_invoice_and_preserves_order_context_in_nested_creation(): void
    {
        $this->actingAs(User::factory()->create());
        $invoice = $this->invoice();
        CompanySetting::first()->update(['sales_tax_profile_id' => $invoice->lines()->first()->tax_profile_id]);
        $order = WorkOrder::factory()->create(['project_id' => Project::factory()->create(['customer_id' => $invoice->customer_id])->id]);
        $phase = WorkOrderPhase::factory()->create(['work_order_id' => $order->id]);
        WorkOrderLine::factory()->create(['work_order_id' => $order->id, 'work_order_phase_id' => $phase->id, 'planned_quantity' => '10']);
        Livewire::test(EditWorkOrder::class, ['record' => $order->id])->callAction('billPhases', ['invoice_series_id' => $invoice->invoice_series_id, 'phase_ids' => [$phase->id], 'percentage' => '25'])->assertHasNoActionErrors()->assertRedirect();
        $draft = SalesInvoice::where('work_order_id', $order->id)->firstOrFail();
        $this->assertSame('2.500000', $draft->lines()->first()->quantity);
        $page = Livewire::test(EditWorkOrder::class, ['record' => $order->id])->mountAction('billPhases');
        $page->callAction(\Filament\Actions\Testing\TestAction::make('createOption')->schemaComponent('phase_ids'), ['code' => 'NUEVA', 'name' => 'Nueva fase', 'work_order_id' => WorkOrder::factory()->create()->id])->assertHasNoFormErrors();
        $this->assertDatabaseHas('work_order_phases', ['code' => 'NUEVA', 'work_order_id' => $order->id]);
    }

    public function test_line_mutations_require_parent_invoice_update_permission(): void
    {
        $this->actingAs(User::factory()->create());
        $invoice = $this->invoice();
        Gate::policy(SalesInvoice::class, DenyBillingUpdates::class);
        $page = Livewire::test(LinesRelationManager::class, ['ownerRecord' => $invoice, 'pageClass' => ViewSalesInvoice::class]);
        $page->assertActionHidden(\Filament\Actions\Testing\TestAction::make('create')->table());
        $page->assertActionHidden(\Filament\Actions\Testing\TestAction::make('edit')->table($invoice->lines()->first()));
        $this->assertDatabaseCount('sales_invoice_lines', 1);
    }

    public function test_final_fractional_credit_uses_remaining_original_cents(): void
    {
        $invoice = $this->invoice(); $invoice->lines()->first()->update(['unit_price' => '0.03']);
        $invoice = app(SalesBilling::class)->issue($invoice);
        $series = InvoiceSeries::factory()->rectifying()->create();
        $first = app(SalesBilling::class)->credit($invoice, $series->id, 'R4', 'Primera mitad');
        $first->lines()->first()->update(['quantity' => '-0.5']);
        $first = app(SalesBilling::class)->issue($first);
        $second = app(SalesBilling::class)->credit($invoice, $series->id, 'R4', 'Resto');
        $second = app(SalesBilling::class)->issue($second);
        $this->assertSame('-0.02', $first->net_total);
        $this->assertSame('-0.01', $second->net_total);
        $this->assertSame('0.00', $first->tax_total);
        $this->assertSame('-0.01', $second->tax_total);
    }
    public function test_a_company_cannot_charge_personal_irpf_even_if_recipient_is_a_withholding_agent(): void
    {
        $invoice = $this->invoice();
        $invoice->lines()->first()->update(['withholding_rate_id' => TaxRate::factory()->create(['kind' => 'withholding', 'percentage' => '15'])->id]);
        $this->expectException(ValidationException::class);
        app(SalesBilling::class)->issue($invoice);
    }

    public function test_fiscal_exemption_omits_vat_fields_and_keeps_legal_reference(): void
    {
        $invoice = $this->invoice();
        $profile = TaxProfile::factory()->create(['operation_class' => 'exempt', 'exemption_code' => 'E1', 'tax_rate_id' => null, 'legal_basis' => 'Artículo 20 LIVA', 'requires_evidence' => true]);
        $invoice->lines()->first()->update(['tax_profile_id' => $profile->id]);
        $invoice->update(['fiscal_evidence' => 'Servicio sujeto a exención, documentación comprobada.']);
        $issued = app(SalesBilling::class)->issue($invoice);
        $this->assertSame('100.00', $issued->gross_total);
        $this->assertStringContainsString('<sf:OperacionExenta>E1</sf:OperacionExenta>', $issued->billingRecord->xml);
        $this->assertStringNotContainsString('<sf:TipoImpositivo>', $issued->billingRecord->xml);
        $this->assertStringNotContainsString('<sf:CuotaRepercutida>', $issued->billingRecord->xml);
    }

    public function test_pending_credits_do_not_release_work_order_quantities_until_issued(): void
    {
        $invoice = $this->invoice();
        CompanySetting::first()->update(['sales_tax_profile_id' => $invoice->lines()->first()->tax_profile_id]);
        $order = WorkOrder::factory()->create(['project_id' => Project::factory()->create(['customer_id' => $invoice->customer_id])->id]);
        $phase = WorkOrderPhase::factory()->create(['work_order_id' => $order->id]);
        $source = WorkOrderLine::factory()->create(['work_order_id' => $order->id, 'work_order_phase_id' => $phase->id, 'planned_quantity' => '10', 'estimated_unit_sale_price' => '20']);
        $billing = app(SalesBilling::class);
        $original = $billing->issue($billing->fromWorkOrder($order, $invoice->invoice_series_id, [$phase->id]));
        $credit = $billing->credit($original, InvoiceSeries::factory()->rectifying()->create()->id, 'R4', 'Trabajos devueltos');
        $this->assertSame('0.000000', $billing->remaining($source->id));
        $billing->issue($credit);
        $this->assertSame('10.000000', $billing->remaining($source->id));
    }

    public function test_inline_order_creation_rejects_a_project_owned_by_another_customer(): void
    {
        $this->actingAs(User::factory()->create());
        $invoice = $this->invoice();
        $project = Project::factory()->create();
        $page = Livewire::test(CreateSalesInvoice::class)->fillForm(['customer_id' => $invoice->customer_id]);
        $page->callAction(\Filament\Actions\Testing\TestAction::make('createOption')->schemaComponent('work_order_id'), ['project_id' => $project->id, 'reference' => 'OT-OTRO', 'name' => 'Otra obra'])->assertHasFormErrors(['project_id']);
        $this->assertDatabaseMissing('work_orders', ['reference' => 'OT-OTRO']);
    }

}

class DenyBillingUpdates
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, SalesInvoice $invoice): bool { return true; }
    public function update(User $user, SalesInvoice $invoice): bool { return false; }
}
