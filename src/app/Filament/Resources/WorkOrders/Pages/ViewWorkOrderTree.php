<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Support\BudgetEditor;
use App\Support\WorkOrderChapterTree;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewWorkOrderTree extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected string $view = 'filament.resources.work-orders.pages.chapter-tree';

    /** @var array<int, array{included: bool, quantity: string, price: string, unit: string}> */
    public array $selection = [];

    public string $budgetName = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->budgetName = 'Presupuesto · '.$this->getRecord()->name;
        foreach (app(WorkOrderChapterTree::class)->snapshots($this->getRecord()) as $item) {
            $this->selection[$item['source_line_id']] = ['included' => true, 'quantity' => $item['quantity'], 'price' => $item['price'], 'unit' => $item['unit']];
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Árbol de capítulos y partidas';
    }

    protected function hasInfolist(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBudget')->label('Crear presupuesto')
                ->authorize(fn (): bool => BudgetResource::canCreate())
                ->action(function (): void {
                    $budget = app(BudgetEditor::class)->create($this->getRecord(), $this->budgetName, $this->selection);
                    Notification::make()->success()->title('Presupuesto creado')->send();
                    $this->redirect(BudgetResource::getUrl('edit', ['record' => $budget]));
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
        $records = $this->getRecord()->workOrderLines()->get()->keyBy('id');
        foreach ($items as &$item) {
            $record = $records->get($item['source_line_id']);
            $item['view_url'] = $record !== null && WorkOrderLineResource::canView($record)
                ? WorkOrderLineResource::getUrl('view', ['record' => $record]) : null;
        }
        unset($item);

        return [
            'items' => $items, 'chapters' => app(WorkOrderChapterTree::class)->group($items),
            'currency' => $this->getRecord()->project->currency_code, 'editingBudget' => false,
            'canBudget' => BudgetResource::canCreate(),
        ];
    }
}
