<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\RelationManagers;

use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use App\Filament\Schemas\CostingForms;
use App\Support\BusinessRecordValidator;
use App\Support\ProjectCosting;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LinesRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'lines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('costing.lines');
    }

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->status !== 'draft';
    }

    public function form(Schema $schema): Schema
    {
        return CostingForms::configure($schema, 'line');
    }

    public function table(Table $table): Table
    {
        $allowed = fn (): bool => PurchaseDeliveryNoteResource::canEdit($this->getOwnerRecord());

        return $table->modelLabel(__('costing.line'))->pluralModelLabel(__('costing.lines'))->recordTitleAttribute('description')->columns([
            TextColumn::make('product.name')->label(__('costing.fields.product_id')), TextColumn::make('description')->label(__('costing.fields.description'))->wrap(),
            TextColumn::make('productUnit.name')->label(__('costing.fields.product_unit_id')), TextColumn::make('quantity')->label(__('costing.fields.quantity')),
            TextColumn::make('unit_price')->label(__('costing.fields.unit_price')), TextColumn::make('base_quantity')->label(__('costing.fields.base_quantity')),
            TextColumn::make('base_unit_cost')->label(__('costing.fields.base_unit_cost')), TextColumn::make('suggested_sale_price')->label(__('costing.fields.suggested_sale_price')),
            TextColumn::make('physical_stock')->label(__('costing.fields.physical_stock'))->state(fn ($record): ?string => $this->isReadOnly() ? app(ProjectCosting::class)->available($record, false) : null),
            TextColumn::make('available_stock')->label(__('costing.fields.available_stock'))->state(fn ($record): ?string => $this->isReadOnly() ? app(ProjectCosting::class)->available($record) : null),
        ])->headerActions([CreateAction::make()->authorize($allowed)->using(fn (array $data): Model => BusinessRecordValidator::inForm(fn (): Model => $this->getRelationship()->create($data), $this->getMountedActionSchema()?->getStatePath() ?? ''))])
            ->recordActions([EditAction::make()->authorize($allowed)->using(function (Model $record, array $data): Model {
                return BusinessRecordValidator::inForm(function () use ($record, $data): Model {
                    $record->update($data);

                    return $record;
                }, $this->getMountedActionSchema()?->getStatePath() ?? '');
            }), DeleteAction::make()->authorize($allowed)])
            ->dragReorderableColumns()->stickableColumns();
    }
}
