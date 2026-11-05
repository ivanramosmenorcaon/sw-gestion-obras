<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Support\BudgetEditor;
use App\Support\BudgetWorkflow;
use App\Support\WorkOrderChapterTree;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class EditBudget extends ViewRecord
{
    protected static string $resource = BudgetResource::class;

    protected string $view = 'filament.resources.work-orders.pages.chapter-tree';

    /** @var array<int, array{included: bool, quantity: string, price: string, unit: string}> */
    public array $selection = [];

    public array $manualLines = [];

    #[Locked]
    public string $fingerprint = '';

    public array $commercialTerms = ['terms' => '', 'exclusions' => '', 'valid_until' => null];

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
            Action::make('saveBudget')->label('Guardar presupuesto')->visible(fn (): bool => $this->getRecord()->status === 'draft')->action(fn () => $this->saveDraft()),
            ...BudgetWorkflow::actions($this),
            Action::make('print')->label('Imprimir')->url(fn (): string => route('documents.print', ['type' => 'budget', 'id' => $this->getRecord()->id]))->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    public function saveDraft(): void
    {
        abort_unless(BudgetResource::canEdit($this->getRecord()), 403);
        app(BudgetEditor::class)->saveDraft($this->getRecord(), $this->budgetName, $this->selection, $this->manualLines, $this->fingerprint, $this->commercialTerms);
        $this->getRecord()->refresh();
        $this->loadEditor();
        Notification::make()->success()->title('Presupuesto guardado')->send();
        $this->dispatch('budget-saved');
    }

    public function addManualLine(): void
    {
        abort_unless(BudgetResource::canEdit($this->getRecord()), 403);
        $this->manualLines[] = ['editor_key' => (string) Str::uuid(), 'description' => '', 'unit' => 'ud', 'quantity' => '1', 'price' => '0', 'discount_percentage' => '0'];
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
        $this->fingerprint = BudgetEditor::fingerprint($this->getRecord());
        $this->commercialTerms = ['terms' => $this->getRecord()->terms ?? '', 'exclusions' => $this->getRecord()->exclusions ?? '', 'valid_until' => $this->getRecord()->valid_until?->toDateString()];
        $this->manualLines = array_values(array_filter($this->getRecord()->items, fn (array $item): bool => $item['manual'] ?? false));
        foreach ($this->manualLines as &$line) {
            $line['editor_key'] = (string) Str::uuid();
        }
        unset($line);
        $this->selection = [];
        foreach ($this->getRecord()->items as $item) {
            $this->selection[$item['source_line_id']] = ['included' => true, 'quantity' => $item['quantity'], 'price' => $item['price'], 'unit' => $item['unit'], 'discount_percentage' => $item['discount_percentage'] ?? '0'];
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
        $items = $this->getRecord()->status === 'draft' ? array_values(array_filter($this->getRecord()->items, fn (array $item): bool => ! ($item['manual'] ?? false))) : $this->getRecord()->items;

        return [
            'items' => $items, 'chapters' => app(WorkOrderChapterTree::class)->group($items),
            'currency' => $this->getRecord()->currency_code, 'editingBudget' => true, 'canBudget' => $this->getRecord()->status === 'draft',
        ];
    }
}
