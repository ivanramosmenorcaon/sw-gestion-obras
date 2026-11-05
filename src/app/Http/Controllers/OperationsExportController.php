<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PaymentReversal;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\WorkTimeEntry;
use App\Support\OperationalAccess;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsExportController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        abort_unless($request->user()?->canAccessPanel(Filament::getCurrentPanel()) && OperationalAccess::canSeeCosts(), 403);
        [$model, $fields] = match ($type) {
            'purchase-lines' => [PurchaseInvoiceLine::class, ['id', 'purchase_invoice_id', 'product_id', 'description', 'unit_label', 'quantity', 'unit_price', 'discount_percentage', 'net_amount', 'tax_percentage', 'tax_amount', 'withholding_amount', 'retention_amount']],
            'refunds' => [PaymentReversal::class, ['id', 'project_id', 'payment_id', 'reference', 'amount', 'operation_date', 'description', 'status']],
            'applications' => [PaymentApplication::class, ['id', 'project_id', 'payment_id', 'sales_invoice_id', 'purchase_invoice_id', 'amount', 'operation_date', 'status']],
            'hours' => [WorkTimeEntry::class, ['id', 'work_order_id', 'work_order_phase_id', 'employee_id', 'contractor_id', 'started_at', 'ended_at', 'break_minutes', 'hours', 'total_cost', 'status']],
            'purchases' => [PurchaseInvoice::class, ['id', 'project_id', 'supplier_id', 'reference', 'operation_date', 'due_date', 'net_amount', 'tax_amount', 'withholding_amount', 'retention_amount', 'payable_total', 'status']],
            'payments' => [Payment::class, ['id', 'project_id', 'party_id', 'sales_invoice_id', 'purchase_invoice_id', 'direction', 'amount', 'operation_date', 'reference', 'status']],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($model, $fields): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $fields, ';', '"', '');
            foreach (OperationalAccess::scope($model::query())->orderBy('id')->cursor() as $record) {
                $values = array_map(function (string $field) use ($record): string {
                    $value = (string) $record->getRawOriginal($field);

                    return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
                }, $fields);
                fputcsv($stream, $values, ';', '"', '');
            }
            fclose($stream);
        }, 'exportacion-'.$type.'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
