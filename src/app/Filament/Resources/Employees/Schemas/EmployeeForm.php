<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EmployeeForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label(__('business.fields.code'))->required()->maxLength(50)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            (array_key_exists('user_id', $context) ? Hidden::make('user_id')->default($context['user_id']) : Select::make('user_id')->relationship('user', 'name')->searchable()->preload())->label(__('business.fields.user_id'))->unique(ignoreRecord: true),
            TextInput::make('email')->label(__('business.fields.email'))->maxLength(255)->email(),
            TextInput::make('phone')->label(__('business.fields.phone'))->maxLength(40)->tel(),
            TextInput::make('job_title')->label(__('business.fields.job_title'))->maxLength(120),
            DatePicker::make('employment_start_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.employment_start_date')),
            DatePicker::make('employment_end_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.employment_end_date'))->afterOrEqual(fn (Get $get): ?string => $get('employment_start_date')),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
        ]);
    }
}
