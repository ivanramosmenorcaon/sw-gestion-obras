<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Budget;
use App\Models\WorkOrder;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListBudgets extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newBudget')->label('Nuevo presupuesto')
                ->authorize(fn (): bool => BudgetResource::canCreate())
                ->schema([
                    Select::make('source_work_order_id')->label('Orden de trabajo')
                        ->relationship('sourceWorkOrder', 'reference')->searchable()->preload()->required(),
                    TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                ])->action(function (array $data): void {
                    $order = WorkOrder::findOrFail($data['source_work_order_id']);
                    abort_unless(WorkOrderResource::canView($order), 403);
                    $budget = Budget::create([
                        'project_id' => $order->project_id, 'source_work_order_id' => $order->id,
                        'reference' => 'PRE-'.Str::ulid(), 'name' => $data['name'],
                        'currency_code' => $order->project->currency_code, 'items' => [],
                    ]);
                    $this->redirect(BudgetResource::getUrl('edit', ['record' => $budget]));
                }),
        ];
    }
}
