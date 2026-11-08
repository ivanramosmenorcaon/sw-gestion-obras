<?php

/**
 * EJEMPLO DE IMPLEMENTACIÓN - Guía para actualizar Relation Managers
 * 
 * Este archivo muestra exactamente cómo implementar el sistema de iconos
 * en tus relation managers existentes.
 */

// PASO 1: Agregar el trait
use App\Filament\Traits\HasIconizedActions;

class TuRelationManager extends RelationManager
{
    use HasIconizedActions;  // ← Agregar esta línea
    
    // ... resto del código
}

// PASO 2: Usar withActionIcons() para tus acciones

// ANTES:
public function table(Table $table): Table
{
    return $table
        ->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ]);
}

// DESPUÉS:
public function table(Table $table): Table
{
    return $table
        ->recordActions([
            $this->withActionIcons(EditAction::make(), 'edit'),
            $this->withActionIcons(DeleteAction::make(), 'delete'),
        ]);
}

// PASO 3: Para crear acciones, también usa iconos:

// ANTES:
->headerActions([
    CreateAction::make(),
])

// DESPUÉS:
->headerActions([
    $this->withActionIcons(CreateAction::make(), 'create'),
])

// PASO 4: Para acciones personalizadas, agrégalas al config

// En config/action-icons.php, agregar:
'custom_action' => [
    'icon' => 'tabler-icon-name',
    'tooltip' => 'Tu descripción aquí',
],

// Y usarla igual que otras:
$this->withActionIcons(YourCustomAction::make(), 'custom_action')

// =============================================================================
// RESUMEN RÁPIDO
// =============================================================================

// 1. Agregar trait al inicio de la clase
use App\Filament\Traits\HasIconizedActions;

// 2. Envolver cada acción con: $this->withActionIcons(Action::make(), 'action_name')
// 3. Los iconos y tooltips vienen del config/action-icons.php
// 4. Cambiar iconos es tan simple como editar el config

// =============================================================================
// ESTRUCTURA GENERAL DE UNA RELATION MANAGER ACTUALIZADA
// =============================================================================

/*

<?php

namespace App\Filament\Resources\YourResource\RelationManagers;

use App\Filament\Traits\HasIconizedActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class YourRelationManager extends RelationManager
{
    use HasIconizedActions;

    protected static string $relationship = 'yourRelationship';

    public function form(Schema $schema): Schema
    {
        return YourForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return YourTable::configure($table)
            ->headerActions([
                $this->withActionIcons(CreateAction::make(), 'create'),
            ])
            ->recordActions([
                $this->withActionIcons(EditAction::make(), 'edit'),
                $this->withActionIcons(DeleteAction::make(), 'delete'),
            ]);
    }
}

*/
