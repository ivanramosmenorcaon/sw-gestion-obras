<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('billing.invoice') }} {{ $invoice->number }}</title>
    <style>
        body { font: 12px sans-serif; color: #111; max-width: 190mm; margin: 15mm auto; }
        h1 { font-size: 23px; } h2 { font-size: 15px; } .parties { display: flex; gap: 30px; margin: 20px 0; }
        .parties section { width: 50%; } table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border-bottom: 1px solid #ccc; padding: 7px 4px; text-align: left; } .number { text-align: right; }
        .warning { border: 2px solid #b91c1c; padding: 12px; font-weight: bold; color: #b91c1c; }
        .qr { width: 35mm; height: 35mm; } tr { break-inside: avoid; } .notes { white-space: pre-wrap; }
        @media print { body { margin: 0; } .controls { display: none; } }
    </style>
</head>
<body>
    <p class="controls"><button onclick="window.print()">{{ __('billing.print') }}</button></p>
    @if ($invoice->environment !== 'production')
        <p class="warning">{{ __('billing.test_warning') }}</p>
    @endif
    <h1>{{ ucfirst(__('billing.invoice')) }} {{ $invoice->number }}</h1>
    <p>{{ __('billing.fields.invoice_type') }}: {{ __('billing.options.'.$invoice->invoice_type) }}</p>
    <p>{{ __('billing.fields.issue_date') }}: {{ $invoice->issue_date->format('d/m/Y') }} · {{ __('billing.fields.operation_date') }}: {{ $invoice->operation_date->format('d/m/Y') }}</p>
    @if ($invoice->due_date)<p>{{ __('billing.fields.due_date') }}: {{ $invoice->due_date->format('d/m/Y') }}</p>@endif
    @if ($invoice->rectifiesInvoice)<p>{{ __('billing.credit') }}: {{ $invoice->rectifiesInvoice->number }} · {{ $invoice->rectifiesInvoice->issue_date->format('d/m/Y') }} (por diferencias)</p>@endif
    <div class="parties">
        <section>
            <h2>{{ $invoice->issuer_snapshot['legal_name'] }}</h2>
            <p>{{ $invoice->issuer_snapshot['tax_identification_number'] }}</p>
            <p>{{ $invoice->issuer_snapshot['address_line_1'] }} {{ $invoice->issuer_snapshot['address_line_2'] ?? '' }}<br>
                {{ $invoice->issuer_snapshot['postal_code'] }} {{ $invoice->issuer_snapshot['city'] }} · {{ $invoice->issuer_snapshot['province'] ?? '' }} · {{ $invoice->issuer_snapshot['country_code'] }}</p>
        </section>
        <section>
            <h2>{{ $invoice->recipient_snapshot['identity']['NombreRazon'] }}</h2>
            <p>{{ $invoice->recipient_snapshot['identity']['NIF'] ?? $invoice->recipient_snapshot['identity']['IDOtro']['ID'] }}</p>
            <p>{{ $invoice->recipient_snapshot['address']['address_line_1'] }} {{ $invoice->recipient_snapshot['address']['address_line_2'] ?? '' }}<br>
                {{ $invoice->recipient_snapshot['address']['postal_code'] }} {{ $invoice->recipient_snapshot['address']['city'] }} · {{ $invoice->recipient_snapshot['address']['province'] ?? '' }} · {{ $invoice->recipient_snapshot['address']['country_code'] }}</p>
        </section>
    </div>
    <p>{{ $invoice->description }}</p>
    <table>
        <thead><tr>@foreach (['description', 'quantity', 'unit_label', 'unit_price', 'discount_percentage', 'net_amount'] as $field)<th>{{ __('billing.fields.'.$field) }}</th>@endforeach</tr></thead>
        <tbody>@foreach ($invoice->lines as $line)<tr><td>{{ $line->description }}
            @if (in_array($line->fiscal_snapshot['operation_class'], ['exempt', 'S2', 'N1', 'N2'], true))
                <br><small>{{ $line->fiscal_snapshot['legal_basis'] }}</small>
            @endif
        </td><td class="number">{{ $line->quantity }}</td><td>{{ $line->unit_label }}</td><td class="number">{{ $line->unit_price }}</td><td class="number">{{ $line->discount_percentage }} %</td><td class="number">{{ $line->net_amount }} €</td></tr>@endforeach</tbody>
    </table>
    <table>
        <thead><tr><th>{{ __('billing.fields.tax_profile_id') }}</th><th>{{ __('billing.fields.net_amount') }}</th><th>{{ __('billing.fields.tax_total') }}</th><th>{{ __('billing.fields.surcharge_total') }}</th></tr></thead>
        <tbody>@foreach ($invoice->tax_summary as $tax)<tr>
            <td>{{ $tax['operation_class'] }} {{ $tax['exemption_code'] ?? '' }}<br>{{ $tax['legal_basis'] }}@if ($tax['operation_class'] === 'S2')<br>Inversión del sujeto pasivo
                @endif
            </td>
            <td>{{ $tax['base'] }} €</td><td>{{ $tax['tax_percentage'] ?? '—' }} % · {{ $tax['tax'] }} €</td><td>{{ $tax['surcharge_percentage'] ?? '—' }} % · {{ $tax['surcharge'] }} €</td>
        </tr>@endforeach</tbody>
    </table>
    @foreach (['net_total', 'tax_total', 'surcharge_total', 'gross_total', 'withholding_total', 'payable_total'] as $field)
        <p class="number"><strong>{{ __('billing.fields.'.$field) }}: {{ $invoice->$field }} €</strong></p>
    @endforeach
    @foreach ($invoice->lines->groupBy(fn ($line) => $line->fiscal_snapshot['withholding_percentage'] ?? '0') as $percentage => $lines)
        @if (\Brick\Math\BigDecimal::of((string) $percentage)->compareTo('0') > 0)<p class="number">IRPF {{ $percentage }} % · {{ \App\Support\InvoiceCalculator::money($lines->reduce(fn ($sum, $line) => $sum->plus($line->withholding_amount), \Brick\Math\BigDecimal::of('0'))) }} €</p>@endif
    @endforeach
    <p class="notes">{{ $invoice->notes }}</p>
    <a href="{{ $qrUrl }}"><img class="qr" src="{{ $qr }}" alt="QR tributario de pruebas"></a>
    <p>VERI*FACTU · {{ __('billing.options.'.$invoice->environment) }}</p>
</body>
</html>
