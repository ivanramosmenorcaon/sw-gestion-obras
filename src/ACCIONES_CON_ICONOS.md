# Sistema de Iconos para Acciones en Relation Managers

Este sistema permite configurar iconos y tooltips para todas las acciones en relation managers de forma centralizada.

## Archivos Creados

1. **`config/action-icons.php`** - Configuración centralizada de iconos y tooltips
2. **`app/Filament/Support/ActionIconsHelper.php`** - Helper para acceder a los iconos
3. **`app/Filament/Traits/HasIconizedActions.php`** - Trait para relation managers
4. **`app/Filament/Widgets/ActionIconsLegendWidget.php`** - Widget para mostrar leyenda en dashboard
5. **`resources/views/filament/widgets/action-icons-legend.blade.php`** - Vista del widget

## Cómo Usar

### 1. Modificar una Relation Manager

**Antes:**
```php
->recordActions([
    EditAction::make(),
    DeleteAction::make(),
])
```

**Después:**
```php
use App\Filament\Traits\HasIconizedActions;

class YourRelationManager extends RelationManager
{
    use HasIconizedActions;

    public function table(Table $table): Table
    {
        return $table
            // ... otras configuraciones
            ->recordActions([
                $this->withActionIcons(EditAction::make(), 'edit'),
                $this->withActionIcons(DeleteAction::make(), 'delete'),
            ]);
    }
}
```

### 2. Personalizar Iconos y Tooltips

Edita `config/action-icons.php` para cambiar cualquier icono o tooltip:

```php
'edit' => [
    'icon' => 'tabler-pencil',  // Cambia el icono
    'tooltip' => 'Editar registro',  // Cambia el tooltip
],
```

Los cambios se aplican automáticamente a todas las relation managers.

### 3. Agregar Nuevas Acciones

Si quieres agregar una acción personalizada, agrega la entrada en `config/action-icons.php`:

```php
'custom_action' => [
    'icon' => 'tabler-icon-name',
    'tooltip' => 'Descripción de la acción',
],
```

Luego úsala en cualquier relation manager:

```php
use App\Filament\Actions\CustomAction; // Tu acción personalizada

$this->withActionIcons(CustomAction::make(), 'custom_action')
```

### 4. Mostrar Leyenda en el Dashboard

Agrega el widget en tu panel dashboard:

```php
// En tu Panel configuration
->widgets([
    \App\Filament\Widgets\ActionIconsLegendWidget::class,
])
```

O en una página específica:

```php
protected static ?string $navigationIcon = 'tabler-dashboard';

public function getHeaderWidgets(): array
{
    return [
        ActionIconsLegendWidget::class,
    ];
}
```

## Iconos Disponibles

El sistema viene con estos iconos pre-configurados:

| Acción | Icono | Tooltip |
|--------|-------|---------|
| create | tabler-plus | Crear nuevo registro |
| edit | tabler-pencil | Editar registro |
| delete | tabler-trash | Eliminar registro |
| view | tabler-eye | Ver detalles |
| restore | tabler-refresh | Restaurar registro |
| force_delete | tabler-trash-x | Eliminar permanentemente |
| duplicate | tabler-copy | Duplicar registro |
| download | tabler-download | Descargar |
| upload | tabler-upload | Cargar archivo |
| export | tabler-file-export | Exportar |
| import | tabler-file-import | Importar |
| approve | tabler-check | Aprobar |
| reject | tabler-x | Rechazar |
| publish | tabler-send | Publicar |
| archive | tabler-box-off | Archivar |

## Ordenar Acciones al Inicio

Las acciones siempre aparecerán al inicio de las filas en la tabla, ya que se definen primero en `recordActions()`.

## Ejemplo Completo

```php
<?php

namespace App\Filament\Resources\YourResource\RelationManagers;

use App\Filament\Traits\HasIconizedActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class YourRelationManager extends RelationManager
{
    use HasIconizedActions;

    protected static string $relationship = 'yourRelationship';

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
                $this->withActionIcons(DeleteAction::make(), 'delete'),
            ]);
    }
}
```

## Modificar Iconos en Tiempo Real

Para cambiar un icono sin reiniciar la aplicación:

```bash
# Edita el archivo de configuración
nano config/action-icons.php

# Los cambios se aplicarán inmediatamente en desarrollo
# En producción, puede que necesites ejecutar:
php artisan config:cache
```

## Tips Útiles

1. **Reutilizar el helper**: Puedes usar `ActionIconsHelper` directamente en cualquier lugar:
   ```php
   use App\Filament\Support\ActionIconsHelper;
   
   $icon = ActionIconsHelper::getIcon('edit');
   $tooltip = ActionIconsHelper::getTooltip('edit');
   $allIcons = ActionIconsHelper::getAllIcons();
   ```

2. **Personalización por acción**: Si una acción específica necesita un icono diferente:
   ```php
   $this->withActionIcons(EditAction::make(), 'edit')
       ->icon('tabler-custom-icon')  // Sobrescribe el icono del config
   ```

3. **Tooltips dinámicos**: Puedes generar tooltips dinámicamente:
   ```php
   $this->withActionIcons(EditAction::make(), 'edit')
       ->tooltip(fn (Model $record) => "Editar {$record->name}")
   ```
