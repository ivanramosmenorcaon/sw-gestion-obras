<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Actions\ImportProjectMeasurements;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\WorkOrder;
use App\Support\OperationalAccess;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MeasurementsRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'measurements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Mediciones';
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return WorkOrderResource::canViewAny() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->modifyQueryUsing(fn (Builder $query): Builder => OperationalAccess::scope($query)->withCount('workOrderLines'))
            ->recordTitleAttribute('name')
            ->recordUrl(fn (WorkOrder $record): string => WorkOrderResource::getUrl('tree', ['record' => $record]))
            ->columns([
                TextColumn::make('reference')->label('Referencia')->searchable()->toggleable(),
                TextColumn::make('name')->label('Mediciones')->searchable()->sortable()->toggleable(),
                TextColumn::make('work_order_lines_count')->label('Partidas')->sortable()->toggleable(),
                TextColumn::make('measurement_source_name')->label('Archivo original')->placeholder('Importación anterior sin archivo archivado')->toggleable(),
                TextColumn::make('created_at')->label('Importado el')->dateTime('d/m/Y H:i', timezone: 'Europe/Madrid')->sortable()->toggleable(),
            ])
            ->headerActions([ImportProjectMeasurements::make($this->getOwnerRecord())])
            ->recordActions([
                Action::make('openMeasurements')->label('Abrir mediciones')->icon('heroicon-o-folder-open')
                    ->authorize(fn (WorkOrder $record): bool => WorkOrderResource::canView($record))
                    ->url(fn (WorkOrder $record): string => WorkOrderResource::getUrl('tree', ['record' => $record])),
            ])
            ->emptyStateHeading('Todavía no hay mediciones importadas')
            ->emptyStateDescription('Importa el BC3 o Excel del arquitecto para consultar capítulos, partidas y desgloses y preparar presupuestos.')
            ->defaultSort('id', 'desc');
    }
}
