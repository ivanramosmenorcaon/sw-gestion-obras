---
name: filament-resizable-columns
description: Aplica la configuración obligatoria de columnas redimensionables, reordenables y fijables al crear o modificar tablas Filament, páginas de listado, table widgets, componentes Livewire con tablas y relation managers de este proyecto. Activar junto con filament-development.
---

# Columnas de tablas Filament

Toda tabla Filament que se cree o modifique en este proyecto debe incluir el trait `Asmit\ResizedColumn\HasResizableColumn` en el componente Livewire que la renderiza y habilitar ambos métodos en su configuración de `Table`:

```php
use Asmit\ResizedColumn\HasResizableColumn;
```

Dentro de la clase del componente:

```php
use HasResizableColumn;
```

En la cadena de configuración de la tabla, conservando las columnas, filtros, acciones y demás opciones existentes:

```php
return $table
    // Configuración existente de la tabla.
    ->dragReorderableColumns()
    ->stickableColumns();
```

## Dónde aplicar cada parte

- **Resources con clases `Tables/*Table.php`:** añadir ambos métodos en la cadena de `configure(Table $table): Table` y el import y uso del trait en la página `Pages/List*.php` que renderiza la tabla. No colocar el trait en la clase auxiliar de configuración ni en el Resource: necesita el ciclo de vida de Livewire.
- **Relation managers:** añadir el import y uso del trait en la clase del relation manager y ambos métodos en su `table(Table $table): Table`. Si delega la configuración a una clase compartida, comprobar que la tabla resultante habilita ambos métodos.
- **Table widgets, páginas personalizadas y componentes Livewire con tablas:** añadir el import y uso del trait al componente que renderiza la tabla y ambos métodos a la cadena de configuración correspondiente.
- Si varias páginas o componentes consumen una tabla compartida, comprobar el trait en cada componente consumidor afectado.

## Comprobación antes de terminar

- Activar esta skill automáticamente al crear o modificar una tabla Filament o un relation manager, sin esperar a que el usuario mencione el paquete.
- Comprobar que están presentes tanto el import como `use HasResizableColumn;` dentro de la clase, o que el trait ya se hereda de una clase base.
- No duplicar imports, traits ni llamadas existentes. Si alguno de los métodos está desactivado explícitamente, habilitarlo para cumplir esta convención salvo instrucción expresa del usuario.
- Usar exactamente `->dragReorderableColumns()` y `->stickableColumns()` sin argumentos. El primero reordena columnas; no sustituirlo por opciones de reordenación de registros.
- Respetar las instrucciones del proyecto sobre versiones, documentación, formato y validación al modificar PHP. Referencia local del paquete: `vendor/asmit/resized-column/README.md`; comprobar la versión instalada antes de depender de su API.
- Aplicar la convención a las tablas del trabajo solicitado. Crear esta skill no implica modificar en bloque todas las tablas existentes; hacerlo cuando el usuario solicite esa migración.
