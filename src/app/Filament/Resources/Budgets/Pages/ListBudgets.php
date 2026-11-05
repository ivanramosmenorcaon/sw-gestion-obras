<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Budget;
use App\Models\Project;
use App\Models\WorkOrder;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
                    Select::make('project_id')->label('Obra')->relationship('project', 'name')->searchable()->preload()->requiredWithout('source_work_order_id'),
                    Select::make('source_work_order_id')->label('Orden de trabajo')
                        ->relationship('sourceWorkOrder', 'reference')->searchable()->preload(),
                    TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                ])->action(function (array $data): void {
                    $order = filled($data['source_work_order_id'] ?? null) ? WorkOrder::findOrFail($data['source_work_order_id']) : null;
                    $project = $order?->project ?? Project::findOrFail($data['project_id']);
                    abort_unless(ProjectResource::canView($project) && (! $order || WorkOrderResource::canView($order)), 403);
                    if ($order && filled($data['project_id'] ?? null) && $order->project_id !== (int) $data['project_id']) {
                        throw ValidationException::withMessages(['project_id' => 'La orden debe pertenecer a la obra seleccionada.']);
                    }
                    $budget = Budget::create([
                        'project_id' => $project->id, 'source_work_order_id' => $order?->id,
                        'reference' => 'PRE-'.Str::ulid(), 'name' => $data['name'],
                        'currency_code' => $project->currency_code, 'items' => [],
                    ]);
                    $this->redirect(BudgetResource::getUrl('edit', ['record' => $budget]));
                }),
        ];
    }
}
