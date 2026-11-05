<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use App\Models\Budget;
use App\Models\CompanySetting;
use App\Models\PurchaseDeliveryNote;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhase;
use App\Models\WorkTimeEntry;
use App\Support\InvoiceCalculator;
use App\Support\WorkOrderChapterTree;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DocumentPrintController extends Controller
{
    public function __invoke(Request $request, string $type, int $id): View
    {
        $types = [
            'purchase-order' => [PurchaseOrder::class, PurchaseOrderResource::class, 'Pedido de compra'],
            'purchase-invoice' => [PurchaseInvoice::class, PurchaseInvoiceResource::class, 'Factura recibida'],
            'budget' => [Budget::class, BudgetResource::class, 'Presupuesto'],
            'order' => [WorkOrder::class, WorkOrderResource::class, 'Orden de trabajo'],
            'phase' => [WorkOrderPhase::class, WorkOrderPhaseResource::class, 'Fase'],
            'time' => [WorkTimeEntry::class, WorkTimeEntryResource::class, 'Parte de trabajo'],
            'delivery' => [PurchaseDeliveryNote::class, PurchaseDeliveryNoteResource::class, 'Albarán'],
        ];
        abort_unless(isset($types[$type]), 404);
        [$model, $resource, $title] = $types[$type];
        $record = $model::findOrFail($id);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        abort_unless($request->user()?->canAccessPanel(Filament::getCurrentPanel()) && $resource::canView($record), 403);
        $items = match ($type) {
            'purchase-order', 'purchase-invoice' => $record->lines()->orderBy('position')->get()->map(fn ($line): array => [
                'description' => $line->description, 'unit' => $line->unit_label, 'quantity' => $line->quantity, 'price' => $line->unit_price,
                'discount' => $line->discount_percentage, 'net' => $line->net_amount,
            ])->all(),
            'budget' => $record->items,
            'order' => app(WorkOrderChapterTree::class)->snapshots($record),
            'phase' => $record->workOrderLines()->orderBy('position')->get()->map(fn ($line): array => [
                'description' => $line->description, 'unit' => $line->unit_label,
                'quantity' => $line->planned_quantity, 'price' => $line->estimated_unit_sale_price, 'discount_percentage' => $line->discount_percentage,
            ])->all(),
            'delivery' => $record->lines()->orderBy('position')->get()->map(fn ($line): array => [
                'description' => $line->description, 'unit' => $line->product_snapshot['presentation'] ?? $line->productUnit?->name ?? $line->product_snapshot['unit'] ?? $line->baseUnit?->symbol,
                'quantity' => $line->quantity, 'price' => $line->unit_price,
                'discount' => $line->discount_percentage,
                'net' => $line->net_total ?? InvoiceCalculator::money(BigDecimal::of($line->quantity)->multipliedBy($line->unit_price)->multipliedBy(BigDecimal::of(100)->minus($line->discount_percentage))->dividedBy(100, 12, RoundingMode::HalfUp)),
            ])->all(),
            default => [],
        };

        $company = CompanySetting::where('scope', 'company')->first();
        $snapshot = $type === 'budget' ? $record->commercial_snapshot : null;
        $customer = in_array($type, ['budget', 'order'], true) ? $record->project->customer : null;

        return view('documents.print', compact('record', 'title', 'items', 'type', 'company', 'snapshot', 'customer'));
    }
}
