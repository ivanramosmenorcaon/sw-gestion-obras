<?php

namespace App\Filament\Resources\WorkOrderLines\Pages;

use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Support\BudgetBreakdownTree;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewWorkOrderLine extends ViewRecord
{
    protected static string $resource = WorkOrderLineResource::class;

    protected string $view = 'filament.resources.work-order-lines.pages.view-work-order-line';

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Partida '.($this->getRecord()->source_code ?? '#'.$this->getRecord()->position);
    }

    protected function hasInfolist(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $line = $this->getRecord()->loadMissing(['project', 'workOrder', 'projectChapter', 'workOrderPhase', 'unit', 'baseUnit', 'product', 'productUnit']);
        $chapters = [];
        $chapter = $line->projectChapter;
        $visited = [];
        while ($chapter !== null && ! isset($visited[$chapter->id]) && count($chapters) < 50) {
            $visited[$chapter->id] = true;
            array_unshift($chapters, $chapter);
            $chapter = $chapter->parent;
        }
        $rows = $line->budget_breakdown ?? [];

        return [
            'line' => $line,
            'nodes' => app(BudgetBreakdownTree::class)->build($rows),
            'chapters' => $chapters,
            'currency' => $line->project->currency_code,
            'title' => explode("\n", $line->description, 2)[0],
            'resourceCount' => count($rows),
            'levelCount' => $rows === [] ? 0 : max(array_column($rows, 'depth')),
        ];
    }
}
