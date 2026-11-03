<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\SupplierProducts\Schemas\SupplierProductForm;
use App\Filament\Resources\SupplierProducts\Tables\SupplierProductsTable;
use App\Support\BusinessRecordValidator;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SupplierProductsRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'supplierProducts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('business.models.supplier_products.plural');
    }

    public function form(Schema $schema): Schema
    {
        return SupplierProductForm::configure($schema, ['product_id' => $this->getOwnerRecord()->getKey()]);
    }

    public function table(Table $table): Table
    {
        return SupplierProductsTable::configure($table)
            ->modelLabel(__('business.models.supplier_products.singular'))
            ->pluralModelLabel(__('business.models.supplier_products.plural'))
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
