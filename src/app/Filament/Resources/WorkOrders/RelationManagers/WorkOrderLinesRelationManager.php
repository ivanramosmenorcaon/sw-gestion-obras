<?php

namespace App\Filament\Resources\WorkOrders\RelationManagers;

use App\Filament\Resources\WorkOrderLines\Schemas\WorkOrderLineForm;
use App\Filament\Resources\WorkOrderLines\Tables\WorkOrderLinesTable;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Models\ProjectItem;
use App\Models\WorkOrder;
use App\Support\BusinessRecordValidator;
use App\Support\OperationalAccess;
use App\Support\ProjectScope;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WorkOrderLinesRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'workOrderLines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Trabajos encargados';
    }

    public function form(Schema $schema): Schema
    {
        return WorkOrderLineForm::configure($schema, ['work_order_id' => $this->getOwnerRecord()->getKey(), 'project_id' => $this->getOwnerRecord()->project_id]);
    }

    public function table(Table $table): Table
    {
        return WorkOrderLinesTable::configure($table)
            ->modelLabel(__('business.models.work_order_lines.singular'))
            ->pluralModelLabel(__('business.models.work_order_lines.plural'))
            ->headerActions([
                Action::make('assignProjectItem')->label('Encargar partida de la obra')
                    ->authorize(fn (): bool => WorkOrderLineResource::canCreate() && OperationalAccess::allows(WorkOrder::class, 'update', $this->getOwnerRecord()) && $this->getOwnerRecord()->purpose === 'intervention')
                    ->schema([
                        Hidden::make('project_id')->default(fn (): int => $this->getOwnerRecord()->project_id),
                        Select::make('project_item_id')->label('Partida de la obra')->relationship('projectItem', 'description', fn (Builder $query): Builder => $query->where('project_id', $this->getOwnerRecord()->project_id))->searchable()->preload()->required()->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                $item = ProjectItem::find($state);
                                if ($item && $item->project_id === $this->getOwnerRecord()->project_id) {
                                    $set('planned_quantity', $item->remainingQuantity());
                                }
                            }),
                        TextInput::make('planned_quantity')->label('Cantidad que se encarga en esta intervención')->numeric()->minValue('0.000001')->step('0.000001')->required(),
                    ])
                    ->action(fn (array $data): Model => BusinessRecordValidator::inForm(fn (): Model => app(ProjectScope::class)->assign($this->getOwnerRecord(), ProjectItem::findOrFail($data['project_item_id']), (string) $data['planned_quantity']), $this->getMountedActionSchema()?->getStatePath() ?? '')),
                CreateAction::make()->label('Crear partida nueva y encargarla')->using(fn (array $data): Model => BusinessRecordValidator::inForm(function () use ($data): Model {
                    return DB::transaction(function () use ($data): Model {
                        $line = $this->getRelationship()->create(array_replace($data, ['project_id' => $this->getOwnerRecord()->project_id]));
                        app(ProjectScope::class)->captureLine($line);

                        return $line;
                    });
                }, $this->getMountedActionSchema()?->getStatePath() ?? '')),
            ])
            ->recordActions([
                WorkOrderLinesTable::breakdownAction(),
                EditAction::make()->using(function (Model $record, array $data): Model {
                    return BusinessRecordValidator::inForm(function () use ($record, $data): Model {
                        $record->update($data);

                        return $record;
                    }, $this->getMountedActionSchema()?->getStatePath() ?? '');
                }),
                DeleteAction::make()->visible(fn (Model $record): bool => BusinessRecordValidator::canDelete($record)),
            ]);
    }
}
