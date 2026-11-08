<?php

namespace App\Filament\Traits;

use App\Filament\Support\ActionIconsHelper;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

trait HasIconizedActions
{
    /**
     * Aplica configuración de iconos y tooltips a una acción
     */
    protected function withActionIcons(Action $action, string $actionName): Action
    {
        return ActionIconsHelper::configureAction($action, $actionName);
    }

    /**
     * Retorna acciones de edición y eliminación con iconos configurados
     */
    protected function getDefaultIconizedActions(): array
    {
        return [
            $this->withActionIcons(EditAction::make(), 'edit'),
            $this->withActionIcons(DeleteAction::make(), 'delete'),
        ];
    }
}
