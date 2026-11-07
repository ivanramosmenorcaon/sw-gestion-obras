<?php

namespace App\Filament\Resources\ProjectItems;

use App\Filament\Clusters\Works\WorksCluster;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\ProjectItems\Pages\ManageProjectItems;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Schemas\ProjectItemForm;
use App\Models\ProjectItem;
use App\Models\WorkOrder;
use App\Support\BudgetEditor;
use App\Support\BusinessRecordValidator;
use App\Support\ProjectScope;
use BackedEnum;
use Brick\Math\BigDecimal;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class ProjectItemResource extends BusinessResource
{
    protected static ?string $model = ProjectItem::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::ListTree;

    protected static ?string $cluster = WorksCluster::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'description';

    public static function getNavigationGroup(): ?string
    {
        return 'Preparación';
    }

    public static function getModelLabel(): string
    {
        return 'Partida de obra';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Partidas de obra';
    }

    public static function canEdit(Model $record): bool
    {
        return ! $record->source_budget_id && ! $record->workOrderLines()->exists() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record) && parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()->stickableColumns()
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project', 'projectChapter', 'sourceBudget'])
                ->withSum(['workOrderLines as allocated_quantity' => fn (Builder $q) => $q->whereHas('workOrder', fn (Builder $q) => $q->where('purpose', 'intervention')->where('status', '!=', 'cancelled'))], 'planned_quantity')
                ->withSum(['progress as executed_quantity' => fn (Builder $q) => $q->where('status', 'posted')], 'quantity')
                ->withSum(['invoiceLines as invoiced_quantity' => fn (Builder $q) => $q->whereHas('invoice', fn (Builder $q) => $q->where('status', 'issued'))], 'quantity'))
            ->columns([
                TextColumn::make('source_code')->label('Código')->searchable()->toggleable(),
                TextColumn::make('description')->label('Partida')->searchable()->wrap(),
                TextColumn::make('project.name')->label('Obra')->searchable()->toggleable(),
                TextColumn::make('projectChapter.name')->label('Capítulo')->toggleable(),
                TextColumn::make('unit_label')->label('Unidad')->toggleable(),
                TextColumn::make('planned_quantity')->label('Cantidad prevista / contratada')->numeric(decimalPlaces: 6, locale: 'es')->toggleable(),
                TextColumn::make('allocated_quantity')->label('Encargado')->default(0)->numeric(decimalPlaces: 6, locale: 'es')->toggleable(),
                TextColumn::make('unallocated')->label('Pendiente de encargar')->state(fn (ProjectItem $record): string => (string) BigDecimal::of($record->planned_quantity)->minus((string) ($record->allocated_quantity ?? 0)))->numeric(decimalPlaces: 6, locale: 'es')->toggleable(),
                TextColumn::make('executed_quantity')->label('Ejecutado confirmado')->default(0)->numeric(decimalPlaces: 6, locale: 'es')->toggleable(),
                TextColumn::make('invoiced_quantity')->label('Facturado emitido (cantidad neta)')->default(0)->numeric(decimalPlaces: 6, locale: 'es')->toggleable(),
                TextColumn::make('sourceBudget.reference')->label('Oferta aceptada de origen')->placeholder('Partida propia / histórica')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('project')->label('Obra')->relationship('project', 'name')->searchable()->preload(),
                SelectFilter::make('chapter')->label('Capítulo')->relationship('projectChapter', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('createIntervention')->label('Crear intervención')
                    ->authorize(fn (ProjectItem $record): bool => static::canView($record) && WorkOrderResource::canCreate() && WorkOrderLineResource::canCreate())
                    ->schema([
                        TextInput::make('name')->label('Intervención concreta')->required()->maxLength(255),
                        TextInput::make('planned_quantity')->label('Cantidad que se encarga')->numeric()->minValue('0.000001')->step('0.000001')->required()->default(fn (ProjectItem $record): string => $record->remainingQuantity()),
                        Textarea::make('instructions')->label('Instrucciones para el equipo'),
                    ])
                    ->action(function (ProjectItem $record, array $data, Component $livewire): void {
                        $order = BusinessRecordValidator::inForm(fn (): WorkOrder => DB::transaction(function () use ($record, $data): WorkOrder {
                            abort_unless(static::canView($record) && WorkOrderResource::canCreate() && WorkOrderLineResource::canCreate(), 403);
                            $order = WorkOrder::create(['project_id' => $record->project_id, 'reference' => 'OT-'.Str::ulid(), 'name' => $data['name'], 'instructions' => $data['instructions'] ?? null]);
                            app(ProjectScope::class)->assign($order, $record, (string) $data['planned_quantity']);

                            return $order;
                        }), 'mountedActions.0.data');
                        $livewire->redirect(WorkOrderResource::getUrl('edit', ['record' => $order]));
                    }),
                EditAction::make()->authorize(fn (ProjectItem $record): bool => static::canEdit($record))->using(fn (ProjectItem $record, array $data): Model => BusinessRecordValidator::inForm(function () use ($record, $data): Model {
                    $record->update($data);

                    return $record;
                }, 'mountedActions.0.data')),
                DeleteAction::make()->authorize(fn (ProjectItem $record): bool => static::canDelete($record)),
            ])
            ->toolbarActions([
                BulkAction::make('prepareBudget')->label('Presupuestar partidas seleccionadas')->authorize(fn (): bool => BudgetResource::canCreate())
                    ->schema([TextInput::make('name')->label('Nombre del presupuesto')->required()->maxLength(255)])
                    ->action(function (Collection $records, array $data, Component $livewire): void {
                        $budget = app(BudgetEditor::class)->fromProjectItems($records, $data['name']);
                        $livewire->redirect(BudgetResource::getUrl('edit', ['record' => $budget]));
                    }),
            ])->defaultSort('position');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProjectItems::route('/'),
        ];
    }
}
