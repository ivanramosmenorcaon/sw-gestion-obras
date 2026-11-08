<?php

namespace App\Filament\Support;

use Filament\Actions\Action;

class ActionIconsHelper
{
    /**
     * Obtiene la configuración de un icono de acción
     */
    public static function getIcon(string $actionName): ?string
    {
        $config = config('action-icons.actions.'.$actionName);

        return $config['icon'] ?? null;
    }

    /**
     * Obtiene el tooltip de una acción
     */
    public static function getTooltip(string $actionName): ?string
    {
        $config = config('action-icons.actions.'.$actionName);

        return $config['tooltip'] ?? null;
    }

    /**
     * Configura un Action con icono y tooltip
     */
    public static function configureAction(Action $action, string $actionName): Action
    {
        $icon = self::getIcon($actionName);
        $tooltip = self::getTooltip($actionName);

        if ($icon) {
            $action->icon($icon);
        }

        if ($tooltip) {
            $action->tooltip($tooltip);
        }

        return $action;
    }

    /**
     * Obtiene todos los iconos configurados (para la leyenda del dashboard)
     */
    public static function getAllIcons(): array
    {
        return config('action-icons.actions', []);
    }
}
