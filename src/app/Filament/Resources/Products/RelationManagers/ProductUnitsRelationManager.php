<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\ProductUnits\Schemas\ProductUnitForm;
use App\Filament\Resources\ProductUnits\Tables\ProductUnitsTable;
use App\Support\BusinessRecordValidator;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductUnitsRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'productUnits';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('business.models.product_units.plural');
    }

    public function form(Schema $schema): Schema
    {
        return ProductUnitForm::configure($schema, ['product_id' => $this->getOwnerRecord()->getKey()]);
    }

    public function table(Table $table): Table
    {
        return ProductUnitsTable::configure($table)
            ->modelLabel(__('business.models.product_units.singular'))
            ->pluralModelLabel(__('business.models.product_units.plural'))
            ->headerActions([
                CreateAction::make()->using(fn (array $data): Model => BusinessRecordValidator::inForm(fn (): Model => $this->getRelationship()->create($data), $this->getMountedActionSchema()?->getStatePath() ?? '')),
            ])
            ->recordActions([
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
