<?php

namespace App\Filament\Resources\Employees\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['workOrders', 'workOrderEmployeeAssignments']))->columns([
                TextColumn::make('code')->searchable()->sortable()->label(__('business.fields.code'))->toggleable(),
                TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
                TextColumn::make('user.name')->searchable()->sortable()->label(__('business.fields.user_id'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')->searchable()->sortable()->label(__('business.fields.email'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->searchable()->sortable()->label(__('business.fields.phone'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('job_title')->searchable()->sortable()->label(__('business.fields.job_title'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employment_start_date')->date('d/m/Y')->sortable()->label(__('business.fields.employment_start_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employment_end_date')->date('d/m/Y')->sortable()->label(__('business.fields.employment_end_date'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
            ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
