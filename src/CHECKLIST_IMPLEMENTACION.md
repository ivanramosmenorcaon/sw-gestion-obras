# ✅ Checklist de Implementación

## Fase 1: Verificación (2 minutos)

- [ ] Verifico que los archivos fueron creados:
  ```bash
  ls -la config/action-icons.php
  ls -la app/Filament/Support/ActionIconsHelper.php
  ls -la app/Filament/Traits/HasIconizedActions.php
  ls -la app/Filament/Widgets/ActionIconsLegendWidget.php
  ```

- [ ] Leo la documentación:
  ```bash
  cat ICONOS_GUIA_PRACTICA.md
  ```

## Fase 2: Primera Implementación (5-10 minutos)

### Opción A: Manual (Recomendado para comenzar)

- [ ] Abro un relation manager, ej: `app/Filament/Resources/Parties/RelationManagers/PartyRolesRelationManager.php`

- [ ] Agrego el import:
  ```php
  use App\Filament\Traits\HasIconizedActions;
  ```

- [ ] Agrego el trait a la clase:
  ```php
  use HasIconizedActions;
  ```

- [ ] Modifico las acciones:
  ```php
  // CAMBIO DE:
  EditAction::make()
  
  // A:
  $this->withActionIcons(EditAction::make(), 'edit')
  ```

- [ ] Hago lo mismo con `DeleteAction::make()` y `CreateAction::make()`

- [ ] Ejecuto Pint:
  ```bash
  vendor/bin/pint --dirty
  ```

- [ ] Abro la aplicación en el navegador y verifico que los iconos aparezcan

### Opción B: Automático (Para todas las relation managers)

- [ ] Ejecuto el comando:
  ```bash
  php artisan icons:apply-to-managers
  ```

- [ ] Ejecuto Pint:
  ```bash
  vendor/bin/pint --dirty
  ```

- [ ] Verifico en el navegador que los iconos aparezcan

## Fase 3: Personalización (2-5 minutos)

- [ ] Abro `config/action-icons.php`

- [ ] Cambio un icono de prueba:
  ```php
  'edit' => [
      'icon' => 'tabler-pencil',  // ← Cambio esto
      'tooltip' => 'Editar registro',
  ],
  ```

- [ ] Guardo el archivo (los cambios se aplican automáticamente en desarrollo)

- [ ] Recargo la página en el navegador y verifico que el icono cambió

- [ ] (Opcional) Cambio otros iconos según tu preferencia

## Fase 4: Agregar Leyenda al Dashboard (2 minutos)

- [ ] Busco mi Panel de Filament (`app/Filament/Panels/AdminPanel.php` o similar)

- [ ] Agrego el widget:
  ```php
  ->widgets([
      \App\Filament\Widgets\ActionIconsLegendWidget::class,
  ])
  ```

- [ ] Recargo el dashboard y verifico que aparezca la leyenda

## Fase 5: Validación (5 minutos)

- [ ] Ejecuto los tests:
  ```bash
  php artisan test tests/Unit/ActionIconsHelperTest.php
  ```

- [ ] ✅ Todos los tests pasan

- [ ] Verifico que no hay errores en el navegador (abre la consola del desarrollador)

- [ ] Pruebo todas las acciones (editar, eliminar, crear, etc.)

## Fase 6: Producción (Opcional)

- [ ] En producción, ejecuto:
  ```bash
  php artisan config:cache
  ```

- [ ] Verifico que todo funciona correctamente

---

## 📋 Casos de Uso Específicos

### Agregar una Acción Personalizada

- [ ] Abro `config/action-icons.php`

- [ ] Agrego una nueva entrada:
  ```php
  'approve' => [
      'icon' => 'tabler-check',
      'tooltip' => 'Aprobar',
  ],
  ```

- [ ] En la relation manager, uso:
  ```php
  $this->withActionIcons(ApproveAction::make(), 'approve')
  ```

### Cambiar el Tooltip de una Acción

- [ ] Abro `config/action-icons.php`

- [ ] Cambio el tooltip:
  ```php
  'delete' => [
      'icon' => 'tabler-trash',
      'tooltip' => 'Eliminar definitivamente',  // ← Cambié esto
  ],
  ```

### Sobrescribir Icono en una Relation Manager Específica

- [ ] En la relation manager:
  ```php
  $this->withActionIcons(EditAction::make(), 'edit')
      ->icon('tabler-custom-icon')  // Sobrescribe el del config
  ```

### Usar Tooltip Dinámico

- [ ] En la relation manager:
  ```php
  $this->withActionIcons(EditAction::make(), 'edit')
      ->tooltip(fn ($record) => "Editar {$record->name}")
  ```

---

## 🐛 Troubleshooting

### Los iconos no aparecen

- [ ] Verifico que el trait `HasIconizedActions` esté agregado a la clase
- [ ] Verifico que use `$this->withActionIcons()` en todas las acciones
- [ ] Ejecuto: `vendor/bin/pint --dirty`
- [ ] Recargo la página: `Ctrl+Shift+R` (hard refresh)

### El comando Artisan no funciona

- [ ] Verifico que el archivo existe: `ls -la app/Console/Commands/ApplyActionIconsToRelationManagers.php`
- [ ] Ejecuto: `php artisan list` para ver si aparece el comando
- [ ] Si no aparece, ejecuto: `php artisan optimize:clear` y luego `php artisan list`

### El widget no aparece en el dashboard

- [ ] Verifico que lo agregué al Panel: `->widgets([ActionIconsLegendWidget::class])`
- [ ] Verifico que el archivo de vista existe: `resources/views/filament/widgets/action-icons-legend.blade.php`
- [ ] Recargo el dashboard: `Ctrl+Shift+R`

### Los cambios en el config no se aplican

En producción, ejecuta:
```bash
php artisan config:cache
```

En desarrollo, simplemente recarga la página.

---

## 📞 Necesitas Ayuda?

Consulta estos archivos:

1. **Para empezar:** `ICONOS_GUIA_PRACTICA.md`
2. **Documentación técnica:** `ACCIONES_CON_ICONOS.md`
3. **Ejemplos básicos:** `EJEMPLO_IMPLEMENTACION_ICONOS.php`
4. **Casos avanzados:** `EJEMPLOS_AVANZADOS_ICONOS.php`
5. **Resumen general:** `RESUMEN_ICONOS.md`

---

## ✨ Una Vez Completado

Una vez que hayas terminado:

- [ ] Todos los iconos aparecen en las relation managers ✓
- [ ] Cada icono tiene un tooltip cuando pasas el mouse ✓
- [ ] El dashboard muestra la leyenda de iconos ✓
- [ ] Puedes cambiar los iconos editando `config/action-icons.php` ✓
- [ ] Los tests pasan ✓
- [ ] No hay errores en la consola del navegador ✓

**¡Felicidades! 🎉 Ya tienes el sistema de iconos completamente funcional.**

---

## 🚀 Próximas Mejoras (Opcionales)

Si quieres ir más allá, puedes:

- [ ] Crear diferentes temas de iconos (light, dark, custom)
- [ ] Agregar animaciones a los iconos
- [ ] Crear un administrador de iconos en el panel
- [ ] Exportar la configuración de iconos a JSON
- [ ] Crear un seeder con la configuración predeterminada
- [ ] Agregar soporte para iconos Font Awesome u otros

---

## 📊 Métricas

Una vez implementado:

- **Relation managers con iconos:** 23 (todas)
- **Iconos predefinidos:** 15
- **Archivos de soporte:** 5 (clases + config)
- **Documentación:** 4 guías
- **Tests:** ✓ Incluidos

**Estado: ✅ Listo para producción**
