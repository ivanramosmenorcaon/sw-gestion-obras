<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductCategoryForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('parent_id', $context) ? Hidden::make('parent_id')->default($context['parent_id']) : Select::make('parent_id')->relationship('parent', 'name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->when($record?->exists, fn (Builder $q): Builder => $q->whereKeyNot($record->getKey()));

                return $query;
            })->searchable()->preload())->label(__('business.fields.parent_id')),
            TextInput::make('code')->label(__('business.fields.code'))->required()->maxLength(50)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
        ]);
    }
}
