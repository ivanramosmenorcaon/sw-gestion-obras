<?php

namespace App\Filament\Clusters\Catalog;

use App\Filament\Clusters\OperationalArea;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;

class CatalogCluster extends OperationalArea
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Books;

    protected static ?string $navigationLabel = 'Catálogo';

    protected static ?string $title = 'Catálogo';

    protected static ?string $slug = 'catalogo';

    protected static ?int $navigationSort = 60;
}
