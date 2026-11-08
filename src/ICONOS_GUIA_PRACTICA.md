# Implementación Sistema de Iconos - Guía Práctica

## Resumen Rápido

✅ **Se crearon 5 archivos:**

1. `config/action-icons.php` - Config centralizada de iconos
2. `app/Filament/Support/ActionIconsHelper.php` - Helper de utilidades
3. `app/Filament/Traits/HasIconizedActions.php` - Trait para relation managers
4. `app/Filament/Widgets/ActionIconsLegendWidget.php` - Widget de leyenda
5. `resources/views/filament/widgets/action-icons-legend.blade.php` - Vista del widget

## Paso 1: Activar en tus Relation Managers

**Opción A: Manual (Recomendado para comenzar)**

Edita una relation manager, por ejemplo `PartyRolesRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Filament\Traits\HasIconizedActions;  // ← Agregar
use App\Filament\Resources\PartyRoles\Schemas\PartyRoleForm;
use App\Filament\Resources\PartyRoles\Tables\PartyRolesTable;
use App\Support\BusinessRecordValidator;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PartyRolesRelationManager extends RelationManager
{
    use HasResizableColumn;
    use HasIconizedActions;  // ← Agregar

    protected static string $relationship = 'partyRoles';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('business.models.party_roles.plural');
    }

    public function form(Schema $schema): Schema
    {
        return PartyRoleForm::configure($schema, ['party_id' => $this->getOwnerRecord()->getKey()]);
    }

    public function table(Table $table): Table
    {
        return PartyRolesTable::configure($table)
            ->modelLabel(__('business.models.party_roles.singular'))
            ->pluralModelLabel(__('business.models.party_roles.plural'))
            ->headerActions([
                $this->withActionIcons(CreateAction::make(), 'create')  // ← Modificar
                    ->using(fn (array $data): Model => BusinessRecordValidator::inForm(
                        fn (): Model => $this->getRelationship()->create($data), 
                        $this->getMountedActionSchema()?->getStatePath() ?? ''
                    )),
            ])
            ->recordActions([
                $this->withActionIcons(EditAction::make(), 'edit')  // ← Modificar
                    ->using(function (Model $record, array $data): Model {
                        return BusinessRecordValidator::inForm(function () use ($record, $data): Model {
                            $record->update($data);
                            return $record;
                        }, $this->getMountedActionSchema()?->getStatePath() ?? '');
                    }),
                $this->withActionIcons(DeleteAction::make(), 'delete')  // ← Modificar
                    ->visible(fn (Model $record): bool => BusinessRecordValidator::canDelete($record)),
            ]);
    }
}
```

**Opción B: Automático (Para todas las relation managers)**

Ejecuta el comando:

```bash
php artisan icons:apply-to-managers
```

> ⚠️ Este comando modifica automáticamente todos los archivos de relation managers.
> Después ejecuta: `vendor/bin/pint --dirty` para formatear el código.

## Paso 2: Personalizar Iconos

Edita `config/action-icons.php`:

```php
'edit' => [
    'icon' => 'tabler-pencil',  // ← Cambia esto
    'tooltip' => 'Editar registro',  // ← O esto
],
```

**Los cambios se aplican inmediatamente.**

## Paso 3: Agregar Leyenda al Dashboard

En tu panel de Filament, agrega el widget:

```php
// app/Filament/Panels/AdminPanel.php (o similar)

->widgets([
    \App\Filament\Widgets\ActionIconsLegendWidget::class,
])
```

## Ejemplo: Agregar una Acción Personalizada

1. **Agrégala al config** (`config/action-icons.php`):

```php
'archive' => [
    'icon' => 'tabler-box-off',
    'tooltip' => 'Archivar registro',
],
```

2. **Úsala en la relation manager**:

```php
use App\Filament\Actions\ArchiveAction;  // Tu acción personalizada

->recordActions([
    $this->withActionIcons(EditAction::make(), 'edit'),
    $this->withActionIcons(DeleteAction::make(), 'delete'),
    $this->withActionIcons(ArchiveAction::make(), 'archive'),  // ← Nueva
])
```

## Iconos Disponibles por Defecto

| Acción | Icono | Descripción |
|--------|-------|-------------|
| `create` | tabler-plus | Crear nuevo |
| `edit` | tabler-pencil | Editar |
| `delete` | tabler-trash | Eliminar |
| `view` | tabler-eye | Ver detalles |
| `restore` | tabler-refresh | Restaurar |
| `force_delete` | tabler-trash-x | Eliminar permanentemente |
| `duplicate` | tabler-copy | Duplicar |
| `download` | tabler-download | Descargar |
| `upload` | tabler-upload | Cargar archivo |
| `export` | tabler-file-export | Exportar |
| `import` | tabler-file-import | Importar |
| `approve` | tabler-check | Aprobar |
| `reject` | tabler-x | Rechazar |
| `publish` | tabler-send | Publicar |
| `archive` | tabler-box-off | Archivar |

> Todos los iconos son de [Tabler Icons](https://tabler-icons.io/).

## Ventajas de este Sistema

✅ **Configuración centralizada** - Cambia todos los iconos en un lugar  
✅ **Reutilizable** - Aplica el mismo icono a múltiples acciones  
✅ **Mantenible** - Solo edita el config, no los archivos de relation managers  
✅ **Escalable** - Agrega nuevas acciones fácilmente  
✅ **Consistente** - Los usuarios siempre ven los mismos iconos  
✅ **Leyenda integrada** - Dashboard muestra qué significa cada icono  

## Próximos Pasos

1. Edita una relation manager manualmente como ejemplo
2. Ejecuta `vendor/bin/pint --dirty` para formatear
3. Abre la aplicación en el navegador y verifica que los iconos aparezcan
4. Si todo funciona, aplica a todas con: `php artisan icons:apply-to-managers`
5. Agrega el widget al dashboard para mostrar la leyenda

## Preguntas Frecuentes

**P: ¿Cómo cambio un icono para una acción específica?**  
R: Edita `config/action-icons.php` y cambia el valor de `icon`.

**P: ¿Puedo tener iconos diferentes para la misma acción en diferentes tablas?**  
R: Sí, puedes sobrescribir en la relation manager:
```php
$this->withActionIcons(EditAction::make(), 'edit')
    ->icon('tabler-custom-icon')  // Sobrescribe el del config
```

**P: ¿Cómo agrego un tooltip personalizado?**  
R: Igual que arriba:
```php
$this->withActionIcons(EditAction::make(), 'edit')
    ->tooltip('Mi tooltip personalizado')
```

**P: ¿Funciona en producción?**  
R: Sí, pero ejecuta `php artisan config:cache` después de cambiar el config.

## Soporte

Para más detalles, mira:
- `ACCIONES_CON_ICONOS.md` - Documentación completa
- `EJEMPLO_IMPLEMENTACION_ICONOS.php` - Ejemplos de código
- `app/Filament/Support/ActionIconsHelper.php` - Fuente del helper
