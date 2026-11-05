<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>{{ $title }} {{ $record->reference ?? $record->code ?? $record->id }}</title>
<style>
body { font: 14px sans-serif; margin: 30px; color: #111; } table { width:100%; border-collapse:collapse; } th,td { text-align:left; padding:8px; border-bottom:1px solid #ccc; white-space:pre-wrap; } tr { break-inside:avoid; } thead { display:table-header-group; } @media print { .controls { display:none; } body { margin:0; } }
</style></head><body>
<button class="controls" onclick="window.print()">Imprimir / Guardar PDF</button>
<p>{{ $snapshot['issuer']['legal_name'] ?? $company?->legal_name }} · {{ $snapshot['issuer']['tax_identification_number'] ?? $company?->tax_identification_number }}</p>
<p>{{ $snapshot['issuer']['address_line_1'] ?? $company?->address_line_1 }} · {{ $snapshot['issuer']['city'] ?? $company?->city }}</p>
@if ($customer)
<p>Cliente: {{ $snapshot['customer']['legal_name'] ?? $customer->legal_name }} · {{ $snapshot['customer']['tax_identification_number'] ?? $customer->tax_identification_number }}</p>
@endif
<h1>{{ $title }} {{ $record->reference ?? $record->code ?? $record->id }}</h1>
<h2>{{ $record->name }}</h2>
<p>{{ $record->description ?? $record->notes }}</p>
@if ($type === 'budget' || $type === 'order')
<p>Obra: {{ $record->project->name }} · {{ $record->currency_code ?? $record->project->currency_code }}</p>
@endif
@if ($type === 'phase' || $type === 'time')
<p>Orden: {{ $record->workOrder->reference }} · {{ $record->workOrder->name }}</p>
@endif
@if ($type === 'time')
<p>Fase: {{ $record->workOrderPhase->name }}</p>
<p>Operario / contratista: {{ $record->employee?->name ?? $record->contractor?->legal_name }}</p>
<p>Inicio: {{ $record->started_at?->timezone('Europe/Madrid')->format('d/m/Y H:i') }} · Fin: {{ $record->ended_at?->timezone('Europe/Madrid')->format('d/m/Y H:i') }} · Pausa: {{ $record->break_minutes }} min</p>
@endif
@if ($type === 'delivery')
<p>Proveedor: {{ $record->supplier->legal_name }} · Fecha: {{ $record->received_date->format('d/m/Y') }}</p>
@endif
@if ($type === 'budget')
<p>Estado: {{ $record->status }} · Revisión: {{ $record->revision }} · Válido hasta: {{ $record->valid_until?->format('d/m/Y') ?? 'Sin fecha indicada' }}</p>
<p>Condiciones: {{ $record->terms }}</p><p>Exclusiones: {{ $record->exclusions }}</p>
@if ($record->accepted_at)<p>Aceptación registrada: {{ $record->accepted_at->format('d/m/Y') }} · {{ $record->acceptance_evidence }}</p>@endif
@endif
@if ($type === 'order')
<p>Instrucciones: {{ $record->instructions }}</p>
<p>Fechas previstas: {{ $record->planned_start_date?->format('d/m/Y') }} — {{ $record->planned_end_date?->format('d/m/Y') }}</p>
<p>Encargado: {{ $record->supervisor?->name }}</p>
@foreach ($record->workOrderEmployeeAssignments as $assignment)<p>Personal: {{ $assignment->employee->name }}</p>@endforeach
@foreach ($record->workOrderContractorAssignments as $assignment)<p>Contratista: {{ $assignment->contractor->legal_name }}</p>@endforeach
@endif
@if ($type !== 'budget') <p>Estado: {{ $record->status }}</p> @endif
@if ($items)
<table><thead><tr><th>Descripción</th><th>Unidad</th><th>Cantidad</th><th>Precio</th><th>Descuento (%)</th><th>Importe neto</th></tr></thead><tbody>
@foreach ($items as $item)
<tr><td>{{ $item['description'] }}
@if (!empty($item['chapter_path']))
@foreach ($item['chapter_path'] as $chapter) {{ $chapter['code'] }} {{ $chapter['name'] }} @endforeach
@endif
@if (!empty($item['measurement_details']))
@foreach ($item['measurement_details'] as $measurement)
{{ is_array($measurement) ? implode(' · ', array_filter($measurement, fn ($value) => is_scalar($value))) : $measurement }}
@endforeach
@endif
</td><td>{{ $item['unit'] }}</td><td>{{ $item['quantity'] }}</td><td>{{ $item['price'] }}</td><td>{{ $item['discount'] ?? $item['discount_percentage'] ?? '0' }}</td><td>{{ \App\Support\BudgetEditor::formatAmount($item['net'] ?? \App\Support\BudgetEditor::amount($item)) }}</td></tr>
@endforeach
</tbody></table>
@if ($type === 'delivery')<p>Total neto: {{ $record->net_total !== null ? \App\Support\BudgetEditor::formatAmount($record->net_total) : 'Pendiente de confirmar' }}</p>@endif
@if (in_array($type, ['purchase-order', 'purchase-invoice']))
<p>Proveedor: {{ $record->supplier->legal_name }} · {{ $record->supplier->tax_identification_number }}</p>
<p>Fecha: {{ $record->operation_date->format('d/m/Y') }} · Vencimiento / entrega: {{ $record->due_date?->format('d/m/Y') }}</p>
<p>Base neta: {{ $record->amount ?? $record->net_amount }} EUR</p>
@if ($type === 'purchase-invoice')
<p>Impuestos: {{ $record->tax_amount }} · Retención fiscal: {{ $record->withholding_amount }} · Garantía: {{ $record->retention_amount }} · A pagar: {{ $record->payable_total }} EUR</p>
@endif
@endif
@if (!in_array($type, ['delivery', 'purchase-order', 'purchase-invoice']))
<p>Total sin impuestos: {{ \App\Support\BudgetEditor::formatAmount(\App\Support\BudgetEditor::total($items)) }}</p>
@endif
@endif
</body></html>
