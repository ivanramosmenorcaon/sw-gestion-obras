<?php

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Filament\Resources\PartyAddresses\Schemas\PartyAddressForm;
use App\Filament\Resources\PartyAddresses\Tables\PartyAddressesTable;
use App\Support\BusinessRecordValidator;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PartyAddressesRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'partyAddresses';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('business.models.party_addresses.plural');
    }

    public function form(Schema $schema): Schema
    {
        return PartyAddressForm::configure($schema, ['party_id' => $this->getOwnerRecord()->getKey()]);
    }

    public function table(Table $table): Table
    {
        return PartyAddressesTable::configure($table)
            ->modelLabel(__('business.models.party_addresses.singular'))
            ->pluralModelLabel(__('business.models.party_addresses.plural'))
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
