<?php

namespace App\Filament\Clusters\Settings;

use App\Filament\Clusters\OperationalArea;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;

class SettingsCluster extends OperationalArea
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Settings;

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $title = 'Configuración';

    protected static ?string $slug = 'configuracion';

    protected static ?int $navigationSort = 70;
}
