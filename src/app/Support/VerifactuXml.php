<?php

namespace App\Support;

use App\Models\BillingRecord;
use App\Models\SalesInvoice;
use Brick\Math\BigDecimal;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class VerifactuXml
{
    public const INFORMATION_NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    public const LEDGER_NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';

    /** @return array<string, string> */
    public function system(): array
    {
        $data = ['NombreRazon' => config('billing.producer_name'), 'NIF' => config('billing.producer_nif'),
            'NombreSistemaInformatico' => config('billing.system_name'), 'IdSistemaInformatico' => config('billing.system_id'),
            'Version' => config('billing.version'), 'NumeroInstalacion' => config('billing.installation'),
            'TipoUsoPosibleSoloVerifactu' => 'S', 'TipoUsoPosibleMultiOT' => 'N', 'IndicadorMultiplesOT' => 'N'];
        Validator::make($data, ['NombreRazon' => ['required', 'string', 'max:120'], 'NIF' => ['required', 'regex:/^[A-Z0-9]{9}$/'],
            'NombreSistemaInformatico' => ['required', 'string', 'max:30'], 'IdSistemaInformatico' => ['required', 'string', 'max:2'],
            'Version' => ['required', 'string', 'max:50'], 'NumeroInstalacion' => ['required', 'string', 'max:100']],
            ['required' => __('billing.messages.system')])->validate();

        return $data;
    }

    public static function hash(string $nif, string $number, string $date, string $type, string $tax, string $gross, string $previousHash, string $generatedAt): string
    {
        $fields = ['IDEmisorFactura' => $nif, 'NumSerieFactura' => $number, 'FechaExpedicionFactura' => $date, 'TipoFactura' => $type,
            'CuotaTotal' => $tax, 'ImporteTotal' => $gross, 'Huella' => $previousHash, 'FechaHoraHusoGenRegistro' => $generatedAt];
        $input = implode('&', array_map(fn (string $key, string $value): string => $key.'='.trim($value), array_keys($fields), array_values($fields)));

        return strtoupper(hash('sha256', $input));
    }

    /** @param array<string, string> $system */
    public function build(SalesInvoice $invoice, ?BillingRecord $previous, array $system, string $generatedAt, string $hash): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElementNS(self::LEDGER_NS, 'sfLR:RegFactuSistemaFacturacion');
        $doc->appendChild($root);
        $header = $doc->createElementNS(self::LEDGER_NS, 'sfLR:Cabecera');
        $root->appendChild($header);
        $this->append($header, ['ObligadoEmision' => ['NombreRazon' => $invoice->issuer_snapshot['legal_name'], 'NIF' => $invoice->issuer_snapshot['tax_identification_number']]]);
        $wrapper = $doc->createElementNS(self::LEDGER_NS, 'sfLR:RegistroFactura');
        $root->appendChild($wrapper);
        $alta = $doc->createElementNS(self::INFORMATION_NS, 'sf:RegistroAlta');
        $wrapper->appendChild($alta);
        $this->append($alta, ['IDVersion' => '1.0', 'IDFactura' => $this->identity($invoice), 'NombreRazonEmisor' => $invoice->issuer_snapshot['legal_name'], 'TipoFactura' => $invoice->invoice_type]);
        if ($invoice->rectifies_invoice_id) {
            $this->append($alta, ['TipoRectificativa' => 'I', 'FacturasRectificadas' => ['IDFacturaRectificada' => $this->identity($invoice->rectifiesInvoice)]]);
        }
        $this->append($alta, ['FechaOperacion' => $invoice->operation_date->format('d-m-Y'), 'DescripcionOperacion' => $invoice->description,
            'Destinatarios' => ['IDDestinatario' => $invoice->recipient_snapshot['identity']]]);
        $breakdown = $doc->createElementNS(self::INFORMATION_NS, 'sf:Desglose');
        $alta->appendChild($breakdown);
        foreach ($invoice->tax_summary as $group) {
            $detail = ['Impuesto' => $group['tax_system'], 'ClaveRegimen' => $group['regime_code']];
            $detail[$group['operation_class'] === 'exempt' ? 'OperacionExenta' : 'CalificacionOperacion'] = $group['operation_class'] === 'exempt' ? $group['exemption_code'] : $group['operation_class'];
            if (in_array($group['operation_class'], ['S1', 'S2'], true)) {
                $detail['TipoImpositivo'] = InvoiceCalculator::money($group['tax_percentage'] ?? '0');
            }
            $detail['BaseImponibleOimporteNoSujeto'] = $group['base'];
            if (in_array($group['operation_class'], ['S1', 'S2'], true)) {
                $detail['CuotaRepercutida'] = $group['tax'];
            }
            if ($group['surcharge_percentage'] !== null) {
                $detail['TipoRecargoEquivalencia'] = InvoiceCalculator::money($group['surcharge_percentage']);
                $detail['CuotaRecargoEquivalencia'] = $group['surcharge'];
            }
            $this->append($breakdown, ['DetalleDesglose' => $detail]);
        }
        $this->append($alta, ['CuotaTotal' => InvoiceCalculator::money(BigDecimal::of($invoice->tax_total)->plus($invoice->surcharge_total)), 'ImporteTotal' => $invoice->gross_total,
            'Encadenamiento' => $previous ? ['RegistroAnterior' => $this->identity($previous->invoice) + ['Huella' => $previous->hash]] : ['PrimerRegistro' => 'S'],
            'SistemaInformatico' => $system, 'FechaHoraHusoGenRegistro' => $generatedAt, 'TipoHuella' => '01', 'Huella' => $hash]);
        $xml = $doc->saveXML();
        $this->validate($xml);

        return $xml;
    }

    /** @return array<string, string> */
    private function identity(SalesInvoice $invoice): array
    {
        return ['IDEmisorFactura' => $invoice->issuer_snapshot['tax_identification_number'], 'NumSerieFactura' => $invoice->number, 'FechaExpedicionFactura' => $invoice->issue_date->format('d-m-Y')];
    }

    /** @param array<string, mixed> $values */
    private function append(DOMElement $parent, array $values): void
    {
        foreach ($values as $key => $value) {
            $child = $parent->ownerDocument->createElementNS(self::INFORMATION_NS, 'sf:'.$key);
            $parent->appendChild($child);
            if (is_array($value)) {
                $this->append($child, $value);
            } else {
                $child->appendChild($parent->ownerDocument->createTextNode((string) $value));
            }
        }
    }

    public function validate(string $xml): void
    {
        $old = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument;
            if (! $doc->loadXML($xml, LIBXML_NONET) || ! $doc->schemaValidate(resource_path('verifactu/SuministroLR.xsd'))) {
                throw ValidationException::withMessages(['lines' => __('billing.messages.xml').': '.implode(' ', array_map(fn ($error): string => trim($error->message), libxml_get_errors()))]);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
    }

    public static function qrUrl(SalesInvoice $invoice): string
    {
        $host = $invoice->environment === 'production' ? 'https://www2.agenciatributaria.gob.es' : 'https://prewww2.aeat.es';

        return $host.'/wlpl/TIKE-CONT/ValidarQR?'.http_build_query(['nif' => $invoice->issuer_snapshot['tax_identification_number'], 'numserie' => $invoice->number,
            'fecha' => $invoice->issue_date->format('d-m-Y'), 'importe' => $invoice->gross_total], '', '&', PHP_QUERY_RFC3986);
    }
}
