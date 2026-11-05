<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use App\Models\Budget;
use App\Models\PurchaseDeliveryNote;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhase;
use App\Models\WorkTimeEntry;
use App\Support\WorkOrderChapterTree;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DocumentPrintController extends Controller
{
    public function __invoke(Request $request, string $type, int $id): View
    {
        $types = [
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
            'budget' => $record->items,
            'order' => app(WorkOrderChapterTree::class)->snapshots($record),
            'phase' => $record->workOrderLines()->orderBy('position')->get()->map(fn ($line): array => [
                'description' => $line->description, 'unit' => $line->unit_label,
                'quantity' => $line->planned_quantity, 'price' => $line->estimated_unit_sale_price,
            ])->all(),
            'delivery' => $record->lines()->orderBy('position')->get()->map(fn ($line): array => [
                'description' => $line->description, 'unit' => $line->productUnit?->name ?? $line->baseUnit?->symbol,
                'quantity' => $line->quantity, 'price' => $line->unit_price,
            ])->all(),
            default => [],
        };

        return view('documents.print', compact('record', 'title', 'items', 'type'));
    }
}
