<?php

namespace App\Filament\Resources\Budgets\Tables;

use App\Models\Budget;
use App\Support\BudgetEditor;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BudgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table->dragReorderableColumns()->stickableColumns()
            ->columns([
                TextColumn::make('reference')->label('Referencia')->searchable()->sortable(),
                TextColumn::make('name')->label('Presupuesto')->searchable(),
                TextColumn::make('project.name')->label('Obra')->searchable()->sortable(),
                TextColumn::make('total')->label('Total sin impuestos')->state(fn (Budget $record): string => BudgetEditor::formatAmount($record->total())),
                TextColumn::make('currency_code')->label('Divisa'),
                TextColumn::make('created_at')->label('Creado')->dateTime('d/m/Y H:i')->sortable(),
            ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
