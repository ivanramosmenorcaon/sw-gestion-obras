<?php

namespace App\Filament\Resources\ProjectChapters\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectChapterForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('project_id', $context) ? Hidden::make('project_id')->default($context['project_id']) : Select::make('project_id')->relationship('project', 'name')->searchable()->preload()->live()->afterStateUpdated(function (Set $set): void {
                $set('work_order_id', null);
                $set('project_chapter_id', null);
                $set('work_order_phase_id', null);
            }))->label(__('business.fields.project_id'))->required(),
            (array_key_exists('parent_id', $context) ? Hidden::make('parent_id')->default($context['parent_id']) : Select::make('parent_id')->relationship('parent', 'name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->where('project_id', $get('project_id'));
                $query->when($record?->exists, fn (Builder $q): Builder => $q->whereKeyNot($record->getKey()));

                return $query;
            })->searchable()->preload())->label(__('business.fields.parent_id')),
            TextInput::make('code')->label(__('business.fields.code'))->required()->maxLength(50),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            Textarea::make('description')->rows(3)->columnSpanFull()->label(__('business.fields.description')),
            TextInput::make('position')->label(__('business.fields.position'))->required()->default('1')->numeric()->minValue(1)->integer(),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
        ]);
    }
}
