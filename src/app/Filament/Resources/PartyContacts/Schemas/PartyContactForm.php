<?php

namespace App\Filament\Resources\PartyContacts\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PartyContactForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('party_id', $context) ? Hidden::make('party_id')->default($context['party_id']) : Select::make('party_id')->relationship('party', 'legal_name')->searchable()->preload())->label(__('business.fields.party_id'))->required(),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            TextInput::make('position')->label(__('business.fields.job_title'))->maxLength(120),
            TextInput::make('email')->label(__('business.fields.email'))->maxLength(255)->email(),
            TextInput::make('phone')->label(__('business.fields.phone'))->maxLength(40)->tel(),
            Toggle::make('is_primary')->label(__('business.fields.is_primary'))->required()->default(false),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
        ]);
    }
}
