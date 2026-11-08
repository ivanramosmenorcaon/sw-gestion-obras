<?php

namespace App\Filament\Widgets;

use App\Filament\Support\ActionIconsHelper;
use Filament\Widgets\Widget;

class ActionIconsLegendWidget extends Widget
{
    protected string $view = 'filament.widgets.action-icons-legend';

    protected static ?int $sort = 1;

    public function getActions(): array
    {
        return ActionIconsHelper::getAllIcons();
    }
}
