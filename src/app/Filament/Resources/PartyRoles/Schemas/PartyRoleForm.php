<?php

namespace App\Filament\Resources\PartyRoles\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class PartyRoleForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('party_id', $context) ? Hidden::make('party_id')->default($context['party_id']) : Select::make('party_id')->relationship('party', 'legal_name')->searchable()->preload())->label(__('business.fields.party_id'))->required(),
            Select::make('role')->options(['customer' => __('business.options.customer'), 'supplier' => __('business.options.supplier'), 'subcontractor' => __('business.options.subcontractor')])->label(__('business.models.party_roles.singular'))->required(),
        ]);
    }
}
