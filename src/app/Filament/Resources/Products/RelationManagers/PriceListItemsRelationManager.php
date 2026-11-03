<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\PriceListItems\Schemas\PriceListItemForm;
use App\Filament\Resources\PriceListItems\Tables\PriceListItemsTable;
use App\Support\BusinessRecordValidator;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PriceListItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'priceListItems';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('business.models.price_list_items.plural');
    }

    public function form(Schema $schema): Schema
    {
        return PriceListItemForm::configure($schema, ['product_id' => $this->getOwnerRecord()->getKey()]);
    }

    public function table(Table $table): Table
    {
        return PriceListItemsTable::configure($table)
            ->modelLabel(__('business.models.price_list_items.singular'))
            ->pluralModelLabel(__('business.models.price_list_items.plural'))
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
