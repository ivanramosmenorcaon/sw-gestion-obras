<?php

namespace App\Filament\Clusters\Team;

use App\Filament\Clusters\OperationalArea;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;

class TeamCluster extends OperationalArea
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Users;

    protected static ?string $navigationLabel = 'Equipo';

    protected static ?string $title = 'Equipo';

    protected static ?string $slug = 'equipo';

    protected static ?int $navigationSort = 30;
}
