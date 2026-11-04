<?php

namespace App\Support;

use App\Models\BillingSubmission;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class VerifactuClient
{
    public function send(BillingSubmission $submission): void
    {
        if (! config('billing.send_enabled')) {
            throw ValidationException::withMessages(['status' => __('billing.messages.sending_disabled')]);
        }
        $certificate = config('billing.certificate_path');
        $key = config('billing.private_key_path');
        if (! $certificate || ! is_readable($certificate) || ! $key || ! is_readable($key)) {
            throw ValidationException::withMessages(['status' => __('billing.messages.certificate')]);
        }
        foreach ([$certificate, $key] as $path) {
            if (str_starts_with(realpath($path), realpath(public_path()).DIRECTORY_SEPARATOR)) {
                throw ValidationException::withMessages(['status' => __('billing.messages.certificate')]);
            }
        }
        $environment = config('billing.environment', 'test');
        $claimed = DB::transaction(function () use ($submission, $environment): ?BillingSubmission {
            DB::table('billing_chains')->where('environment', $environment)->lockForUpdate()->first();
            BillingSubmission::inEnvironment($environment)->where('status', 'sending')->where('claimed_at', '<', now()->subMinutes(5))->update(['status' => 'transport_error', 'claimed_at' => null]);
            if (BillingSubmission::inEnvironment($environment)->where('status', 'sending')->exists() || BillingSubmission::inEnvironment($environment)->where('retry_after', '>', now())->exists()) {
                return null;
            }
            $submission = BillingSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if (! in_array($submission->status, ['pending', 'transport_error'], true) || ($submission->retry_after && $submission->retry_after->isFuture())) {
                return null;
            }
            if ($submission->record->invoice->environment !== $environment) {
                throw ValidationException::withMessages(['status' => __('billing.messages.environment_mismatch')]);
            }
            $submission->update(['status' => 'sending', 'claimed_at' => now(), 'attempts' => $submission->attempts + 1]);

            return $submission;
        });
        if (! $claimed) {
            return;
        }
        $responseBody = null;
        try {
            $body = $this->envelope($claimed->record->xml);
            $response = Http::connectTimeout(10)->timeout(45)->withOptions(['cert' => [$certificate, config('billing.certificate_password') ?? ''], 'ssl_key' => [$key, config('billing.private_key_password') ?? '']])
                ->withHeaders(['SOAPAction' => ''])->withBody($body, 'text/xml; charset=UTF-8')
                ->post($environment === 'production'
                    ? 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP'
                    : 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP');
            $responseBody = $response->body();
            $response->throw();
            $values = $this->parse($responseBody, $claimed);
        } catch (Throwable $exception) {
            $values = ['status' => 'transport_error', 'error_code' => null, 'error_description' => __('billing.messages.transport_error'), 'retry_after' => now()->addSeconds(60)];
        }
        DB::transaction(function () use ($claimed, $values, $responseBody): void {
            $claimed->update($values + ['response_xml' => $responseBody, 'claimed_at' => null]);
            DB::table('billing_submission_attempts')->insert(['billing_submission_id' => $claimed->id, 'attempt' => $claimed->attempts,
                'status' => $values['status'], 'response_xml' => $responseBody, 'created_at' => now()]);
        });
    }

    public function envelope(string $xml): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $envelope = $doc->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $doc->appendChild($envelope);
        $body = $doc->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Body');
        $envelope->appendChild($body);
        $payload = new DOMDocument;
        $payload->loadXML($xml, LIBXML_NONET);
        $body->appendChild($doc->importNode($payload->documentElement, true));

        return $doc->saveXML();
    }

    /** @return array<string, mixed> */
    public function parse(string $xml, BillingSubmission $submission): array
    {
        $old = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument;
            if (! $doc->loadXML($xml, LIBXML_NONET) || $doc->doctype) {
                throw new RuntimeException('Invalid response');
            }
            $path = new DOMXPath($doc);
            $path->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $path->registerNamespace('r', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd');
            $path->registerNamespace('sf', VerifactuXml::INFORMATION_NS);
            if ($path->query('/soap:Envelope/soap:Body/soap:Fault')->length) {
                return ['status' => 'rejected', 'error_code' => 'SOAP_FAULT', 'error_description' => mb_substr($path->evaluate('string(/soap:Envelope/soap:Body/soap:Fault/faultstring)'), 0, 1500), 'retry_after' => now()->addMinute()];
            }
            $prefix = '/soap:Envelope/soap:Body/r:RespuestaRegFactuSistemaFacturacion';
            $root = $path->query($prefix)->item(0);
            $payload = new DOMDocument;
            if (! $root) {
                throw new RuntimeException('Missing response');
            }
            $payload->appendChild($payload->importNode($root, true));
            if (! $payload->schemaValidate(resource_path('verifactu/RespuestaSuministro.xsd'))) {
                throw new RuntimeException('Invalid response schema');
            }

            $lines = $path->query($prefix.'/r:RespuestaLinea');
            if ($lines->length !== 1) {
                throw new RuntimeException('Missing unique response');
            }
            $base = $prefix.'/r:RespuestaLinea';
            $read = fn (string $query): string => $path->evaluate('string('.$query.')');
            $invoice = $submission->record->invoice;
            if ($read($base.'/r:IDFactura/sf:NumSerieFactura') !== $invoice->number || $read($base.'/r:IDFactura/sf:IDEmisorFactura') !== $invoice->issuer_snapshot['tax_identification_number'] || $read($base.'/r:IDFactura/sf:FechaExpedicionFactura') !== $invoice->issue_date->format('d-m-Y')) {
                throw new RuntimeException('Mismatched response');
            }
            $status = match ($read($base.'/r:EstadoRegistro')) {
                'Correcto' => 'accepted', 'AceptadoConErrores' => 'accepted_with_errors', 'Incorrecto' => 'rejected', default => throw new RuntimeException('Unknown status')
            };
            if ($path->query($base.'/r:RegistroDuplicado')->length > 0) {
                $status = 'duplicate_review';
            }

            return ['status' => $status, 'csv' => $read($prefix.'/r:CSV') ?: null, 'error_code' => $read($base.'/r:CodigoErrorRegistro') ?: null,
                'error_description' => $read($base.'/r:DescripcionErrorRegistro') ?: null, 'retry_after' => now()->addSeconds(max(60, (int) $read($prefix.'/r:TiempoEsperaEnvio')))];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
    }
}
