<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Support\BudgetEditor;
use App\Support\WorkOrderChapterTree;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewWorkOrderTree extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected string $view = 'filament.resources.work-orders.pages.chapter-tree';

    /** @var array<int, array{included: bool, quantity: string, price: string, unit: string}> */
    public array $selection = [];

    public string $budgetName = '';

    #[Locked]
    public ?int $selectedLineId = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->selectedLineId = $this->getRecord()->workOrderLines()->orderBy('position')->value('id');
        $this->budgetName = 'Presupuesto · '.$this->getRecord()->name;
        foreach (app(WorkOrderChapterTree::class)->snapshots($this->getRecord()) as $item) {
            $this->selection[$item['source_line_id']] = ['included' => true, 'quantity' => $item['quantity'], 'price' => $item['price'], 'unit' => $item['unit'], 'discount_percentage' => $item['discount_percentage'] ?? '0'];
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->purpose === 'measurement' ? 'Mediciones · '.$this->getRecord()->name : 'Presupuesto desde mediciones';
    }

    public function getView(): string
    {
        return $this->getRecord()->purpose === 'measurement'
            ? 'filament.resources.work-orders.pages.measurements' : $this->view;
    }

    public function getBreadcrumbs(): array
    {
        if ($this->getRecord()->purpose !== 'measurement') {
            return parent::getBreadcrumbs();
        }

        return [
            ProjectResource::getUrl('index') => 'Obras',
            ProjectResource::getUrl('edit', ['record' => $this->getRecord()->project_id]) => $this->getRecord()->project->name,
            'Mediciones',
        ];
    }

    public function selectLine(int $lineId): void
    {
        abort_unless(WorkOrderResource::canView($this->getRecord()), 403);
        abort_unless($this->getRecord()->workOrderLines()->whereKey($lineId)->exists(), 404);
        $this->selectedLineId = $lineId;
    }

    protected function hasInfolist(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadSource')->label('Descargar original')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => filled($this->getRecord()->measurement_source_path))
                ->authorize(fn (): bool => WorkOrderResource::canView($this->getRecord()))
                ->action(function (): StreamedResponse {
                    $record = $this->getRecord();
                    abort_unless(filled($record->measurement_source_path) && Storage::disk('local')->exists($record->measurement_source_path), 404);

                    return Storage::disk('local')->download($record->measurement_source_path, $record->measurement_source_name);
                }),
            Action::make('print')->label('Imprimir')->url(fn (): string => route('documents.print', ['type' => 'order', 'id' => $this->getRecord()->id]))->openUrlInNewTab(),
            Action::make('createBudget')->label('Crear presupuesto')
                ->authorize(fn (): bool => BudgetResource::canCreate() && WorkOrderResource::canView($this->getRecord()))
                ->action(function (): void {
                    $budget = app(BudgetEditor::class)->create($this->getRecord(), $this->budgetName, $this->selection);
                    Notification::make()->success()->title('Presupuesto creado')->send();
                    $this->dispatch('budget-created', url: BudgetResource::getUrl('edit', ['record' => $budget]));
                }),
        ];
    }

    public function toggleChapter(string $key, bool $included): void
    {
        abort_unless(WorkOrderResource::canView($this->getRecord()), 403);
        foreach (app(WorkOrderChapterTree::class)->snapshots($this->getRecord()) as $item) {
            if (($key === 'none' && $item['chapter_path'] === []) || collect($item['chapter_path'])->contains(fn (array $chapter): bool => (string) $chapter['id'] === $key)) {
                $this->selection[$item['source_line_id']]['included'] = $included;
            }
        }
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $items = app(WorkOrderChapterTree::class)->snapshots($this->getRecord());
        if ($this->getRecord()->purpose !== 'measurement') {
            $records = $this->getRecord()->workOrderLines()->get()->keyBy('id');
            foreach ($items as &$item) {
                $record = $records->get($item['source_line_id']);
                $item['view_url'] = $record !== null && WorkOrderLineResource::canView($record)
                    ? WorkOrderLineResource::getUrl('view', ['record' => $record]) : null;
            }
            unset($item);
        }

        return [
            'selectedItem' => collect($items)->firstWhere('source_line_id', $this->selectedLineId),
            'items' => $items, 'chapters' => app(WorkOrderChapterTree::class)->group($items),
            'currency' => $this->getRecord()->project->currency_code, 'editingBudget' => false,
            'canBudget' => BudgetResource::canCreate(),
        ];
    }
}
