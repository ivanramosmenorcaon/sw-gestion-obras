<?php

namespace App\Filament\Clusters\Works;

use App\Filament\Clusters\OperationalArea;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;

class WorksCluster extends OperationalArea
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::BuildingEstate;

    protected static ?string $navigationLabel = 'Obras';

    protected static ?string $title = 'Obras';

    protected static ?string $slug = 'obras';

    protected static ?int $navigationSort = 10;
}
