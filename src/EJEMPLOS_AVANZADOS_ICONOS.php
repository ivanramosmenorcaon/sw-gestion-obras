<?php

/**
 * EJEMPLOS AVANZADOS - Casos de Uso Específicos
 */

// ============================================================================
// 1. RELATION MANAGER CON ACCIONES PERSONALIZADAS
// ============================================================================

namespace App\Filament\Resources\WorkOrders\RelationManagers;

use App\Filament\Actions\ApproveWorkOrderAction;
use App\Filament\Actions\RejectWorkOrderAction;
use App\Filament\Traits\HasIconizedActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class WorkOrderPhasesRelationManager extends RelationManager
{
    use HasIconizedActions;

    protected static string $relationship = 'phases';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // tus columnas aquí
            ])
            ->headerActions([
                $this->withActionIcons(CreateAction::make(), 'create'),
            ])
            ->recordActions([
                $this->withActionIcons(EditAction::make(), 'edit'),
                $this->withActionIcons(ApproveWorkOrderAction::make(), 'approve'),
                $this->withActionIcons(RejectWorkOrderAction::make(), 'reject'),
                $this->withActionIcons(DeleteAction::make(), 'delete'),
            ]);
    }
}

// ============================================================================
// 2. RELATION MANAGER CON ACCIONES CONDICIONALES
// ============================================================================

class ConditionalActionsRelationManager extends RelationManager
{
    use HasIconizedActions;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // tus columnas
            ])
            ->recordActions([
                $this->withActionIcons(EditAction::make(), 'edit'),

                // Mostrar "Aprobar" solo si está pendiente
                $this->withActionIcons(
                    ApproveWorkOrderAction::make(),
                    'approve'
                )->visible(fn ($record) => $record->status === 'pending'),

                // Mostrar "Restaurar" solo si está eliminado
                $this->withActionIcons(
                    RestoreAction::make(),
                    'restore'
                )->visible(fn ($record) => $record->trashed()),

                // Eliminar siempre visible
                $this->withActionIcons(DeleteAction::make(), 'delete'),
            ]);
    }
}

// ============================================================================
// 3. AGREGAR NUEVA ACCIÓN AL CONFIG
// ============================================================================

// Paso 1: Edita config/action-icons.php
/*
    'approve' => [
        'icon' => 'tabler-check',
        'tooltip' => 'Aprobar',
    ],
    'reject' => [
        'icon' => 'tabler-x',
        'tooltip' => 'Rechazar',
    ],
    'archive' => [
        'icon' => 'tabler-box-off',
        'tooltip' => 'Archivar',
    ],
*/

// Paso 2: Usa en relation manager
// $this->withActionIcons(ApproveAction::make(), 'approve')

// ============================================================================
// 4. RELATION MANAGER CON ACCIONES BULK
// ============================================================================

class BulkActionsRelationManager extends RelationManager
{
    use HasIconizedActions;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                CheckboxColumn::make(),  // Checkbox para seleccionar
                // tus columnas
            ])
            ->headerActions([
                $this->withActionIcons(CreateAction::make(), 'create'),
            ])
            ->recordActions([
                $this->withActionIcons(EditAction::make(), 'edit'),
                $this->withActionIcons(DeleteAction::make(), 'delete'),
            ])
            // Acciones para múltiples registros seleccionados
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    // Agregar más bulk actions según sea necesario
                ]),
            ]);
    }
}

// ============================================================================
// 5. PERSONALIZAR TOOLTIP POR REGISTRO
// ============================================================================

class CustomTooltipRelationManager extends RelationManager
{
    use HasIconizedActions;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // tus columnas
            ])
            ->recordActions([
                // Tooltip dinámico basado en el registro
                $this->withActionIcons(EditAction::make(), 'edit')
                    ->tooltip(fn ($record) => "Editar {$record->name}"),

                // Tooltip estándar
                $this->withActionIcons(DeleteAction::make(), 'delete'),
            ]);
    }
}

// ============================================================================
// 6. SOBRESCRIBIR ICONO POR REGISTRO
// ============================================================================

class OverrideIconRelationManager extends RelationManager
{
    use HasIconizedActions;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // tus columnas
            ])
            ->recordActions([
                // Cambiar icono según estado del registro
                $this->withActionIcons(EditAction::make(), 'edit')
                    ->icon(fn ($record) => $record->status === 'draft'
                        ? 'tabler-pencil'      // Editar normal
                        : 'tabler-eye'         // Solo ver en otros estados
                    ),

                $this->withActionIcons(DeleteAction::make(), 'delete'),
            ]);
    }
}

// ============================================================================
// 7. USAR HELPER DIRECTAMENTE EN COMPONENTES
// ============================================================================

use App\Filament\Support\ActionIconsHelper;

class CustomComponent
{
    public function render()
    {
        $editIcon = ActionIconsHelper::getIcon('edit');
        $editTooltip = ActionIconsHelper::getTooltip('edit');
        $allIcons = ActionIconsHelper::getAllIcons();

        return view('your-view', [
            'editIcon' => $editIcon,
            'editTooltip' => $editTooltip,
            'allIcons' => $allIcons,
        ]);
    }
}

// ============================================================================
// 8. CREAR TRAIT PERSONALIZADO PARA ACCIONES ESPECÍFICAS
// ============================================================================

namespace App\Filament\Traits;

trait HasWorkOrderActions
{
    /**
     * Retorna un grupo de acciones personalizadas para órdenes de trabajo
     */
    protected function getWorkOrderActions(): array
    {
        return [
            $this->withActionIcons(EditAction::make(), 'edit'),
            $this->withActionIcons(ApproveWorkOrderAction::make(), 'approve'),
            $this->withActionIcons(RejectWorkOrderAction::make(), 'reject'),
            $this->withActionIcons(ArchiveAction::make(), 'archive'),
            $this->withActionIcons(DeleteAction::make(), 'delete'),
        ];
    }
}

// Luego usarlo:
class SomeRelationManager extends RelationManager
{
    use HasIconizedActions;
    use HasWorkOrderActions;

    public function table(Table $table): Table
    {
        return $table
            ->recordActions($this->getWorkOrderActions());
    }
}

// ============================================================================
// 9. TESTING - VERIFICAR ICONOS EN TESTS
// ============================================================================

namespace Tests\Feature;

use App\Filament\Support\ActionIconsHelper;
use PHPUnit\Framework\TestCase;

class ActionIconsTest extends TestCase
{
    public function test_icons_are_configured()
    {
        $this->assertNotNull(ActionIconsHelper::getIcon('edit'));
        $this->assertNotNull(ActionIconsHelper::getIcon('delete'));
        $this->assertNotNull(ActionIconsHelper::getIcon('create'));
    }

    public function test_tooltips_are_configured()
    {
        $this->assertNotNull(ActionIconsHelper::getTooltip('edit'));
        $tooltip = ActionIconsHelper::getTooltip('edit');
        $this->assertIsString($tooltip);
        $this->assertNotEmpty($tooltip);
    }

    public function test_all_icons_have_tooltips()
    {
        $icons = ActionIconsHelper::getAllIcons();
        foreach ($icons as $actionName => $config) {
            $this->assertArrayHasKey('icon', $config);
            $this->assertArrayHasKey('tooltip', $config);
            $this->assertNotEmpty($config['icon']);
            $this->assertNotEmpty($config['tooltip']);
        }
    }
}

// ============================================================================
// 10. MIGRANDO RELATION MANAGERS EXISTENTES
// ============================================================================

/*
PASOS PARA MIGRAR MANUALMENTE:

1. Abre la relation manager que quieres actualizar
2. Agrega el import: use App\Filament\Traits\HasIconizedActions;
3. Agrega el trait: use HasIconizedActions;
4. Envuelve cada acción:

   ANTES:
   EditAction::make()

   DESPUÉS:
   $this->withActionIcons(EditAction::make(), 'edit')

5. Ejecuta: vendor/bin/pint --dirty
6. Prueba en el navegador
7. Repite para otras relation managers

O ejecuta automáticamente:
   php artisan icons:apply-to-managers
   vendor/bin/pint --dirty
*/
