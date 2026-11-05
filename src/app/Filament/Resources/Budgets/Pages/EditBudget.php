<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Support\BudgetEditor;
use App\Support\WorkOrderChapterTree;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditBudget extends ViewRecord
{
    protected static string $resource = BudgetResource::class;

    protected string $view = 'filament.resources.work-orders.pages.chapter-tree';

    /** @var array<int, array{included: bool, quantity: string, price: string, unit: string}> */
    public array $selection = [];

    public array $manualLines = [];

    public string $budgetName = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->loadEditor();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(BudgetResource::canEdit($this->getRecord()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Presupuesto '.$this->getRecord()->reference;
    }

    protected function hasInfolist(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveBudget')->label('Guardar presupuesto')->action(function (): void {
                abort_unless(BudgetResource::canEdit($this->getRecord()), 403);
                app(BudgetEditor::class)->update($this->getRecord(), $this->budgetName, $this->selection);
                $this->getRecord()->refresh();
                $this->loadEditor();
                Notification::make()->success()->title('Presupuesto guardado')->send();
            }),
            Action::make('saveManual')->label('Guardar líneas manuales')->action(function (): void {
                abort_unless(BudgetResource::canEdit($this->getRecord()), 403);
                app(BudgetEditor::class)->saveManualLines($this->getRecord(), $this->manualLines);
                $this->getRecord()->refresh();
                $this->loadEditor();
                Notification::make()->success()->title('Líneas guardadas')->send();
            }),
            Action::make('print')->label('Imprimir')->url(fn (): string => route('documents.print', ['type' => 'budget', 'id' => $this->getRecord()->id]))->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    public function addManualLine(): void
    {
        abort_unless(BudgetResource::canEdit($this->getRecord()), 403);
        $this->manualLines[] = ['description' => '', 'unit' => 'ud', 'quantity' => '1', 'price' => '0'];
    }

    public function removeManualLine(int $index): void
    {
        abort_unless(BudgetResource::canEdit($this->getRecord()), 403);
        unset($this->manualLines[$index]);
        $this->manualLines = array_values($this->manualLines);
        $this->resetValidation();
    }

    public function removeBudgetLine(int $id): void
    {
        abort_unless(BudgetResource::canEdit($this->getRecord()), 403);
        abort_unless(array_key_exists($id, $this->selection), 404);
        $this->selection[$id]['included'] = false;
    }

    private function loadEditor(): void
    {
        $this->budgetName = $this->getRecord()->name;
        $this->manualLines = array_values(array_filter($this->getRecord()->items, fn (array $item): bool => $item['manual'] ?? false));
        $this->selection = [];
        foreach ($this->getRecord()->items as $item) {
            $this->selection[$item['source_line_id']] = ['included' => true, 'quantity' => $item['quantity'], 'price' => $item['price'], 'unit' => $item['unit']];
        }
    }

    public function toggleChapter(string $key, bool $included): void
    {
        abort_unless(BudgetResource::canEdit($this->getRecord()), 403);
        foreach ($this->getRecord()->items as $item) {
            if (($key === 'none' && $item['chapter_path'] === []) || collect($item['chapter_path'])->contains(fn (array $chapter): bool => (string) $chapter['id'] === $key)) {
                $this->selection[$item['source_line_id']]['included'] = $included;
            }
        }
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $items = $this->getRecord()->items;

        return [
            'items' => $items, 'chapters' => app(WorkOrderChapterTree::class)->group($items),
            'currency' => $this->getRecord()->currency_code, 'editingBudget' => true, 'canBudget' => true,
        ];
    }
}
