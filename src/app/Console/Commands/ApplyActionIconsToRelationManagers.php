<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ApplyActionIconsToRelationManagers extends Command
{
    protected $signature = 'icons:apply-to-managers';

    protected $description = 'Aplica automáticamente el sistema de iconos a todas las relation managers';

    public function handle(): int
    {
        $relationManagersPath = base_path('app/Filament/Resources/*/RelationManagers/*RelationManager.php');
        $files = glob($relationManagersPath);

        if (empty($files)) {
            $this->error('No se encontraron relation managers.');

            return 1;
        }

        $this->info('Se encontraron '.count($files).' relation managers.');
        $updatedCount = 0;

        foreach ($files as $file) {
            if ($this->updateRelationManager($file)) {
                $updatedCount++;
                $this->line('✓ Actualizado: '.basename($file));
            }
        }

        $this->info("\n✓ Se actualizaron {$updatedCount} relation managers.");
        $this->info("\n⚠ Asegúrate de revisar los cambios manualmente:");
        $this->info('  1. Verifica que las acciones estén correctamente configuradas');
        $this->info('  2. Ejecuta: vendor/bin/pint --dirty');
        $this->info('  3. Ejecuta tus tests para asegurar que todo funciona');

        return 0;
    }

    private function updateRelationManager(string $file): bool
    {
        $content = File::get($file);
        $originalContent = $content;

        // Ya tiene el trait
        if (strpos($content, 'HasIconizedActions') !== false) {
            return false;
        }

        // Agregar el trait
        if (strpos($content, 'use Filament') !== false) {
            $content = preg_replace(
                '/(use Filament[^;]*;)/',
                '$1'."\nuse App\Filament\Traits\HasIconizedActions;",
                $content,
                1
            );
        }

        // Agregar el trait a la clase
        if (strpos($content, 'class ') !== false) {
            $content = preg_replace(
                '/(class \w+ extends RelationManager)\n\{/',
                '$1'."\n{\n    use HasIconizedActions;",
                $content,
                1
            );
        }

        // Envolver EditAction con withActionIcons
        $content = preg_replace(
            '/EditAction::make\(\)/',
            '$this->withActionIcons(EditAction::make(), \'edit\')',
            $content
        );

        // Envolver DeleteAction con withActionIcons
        $content = preg_replace(
            '/DeleteAction::make\(\)/',
            '$this->withActionIcons(DeleteAction::make(), \'delete\')',
            $content
        );

        // Envolver CreateAction con withActionIcons
        $content = preg_replace(
            '/CreateAction::make\(\)/',
            '$this->withActionIcons(CreateAction::make(), \'create\')',
            $content
        );

        if ($content !== $originalContent) {
            File::put($file, $content);

            return true;
        }

        return false;
    }
}
