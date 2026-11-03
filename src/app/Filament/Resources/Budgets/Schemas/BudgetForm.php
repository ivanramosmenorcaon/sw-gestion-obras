<?php

namespace App\Filament\Resources\Budgets\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BudgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('Nombre del presupuesto')->required()->maxLength(255)]);
    }
}
