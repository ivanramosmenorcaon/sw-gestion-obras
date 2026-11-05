<?php

namespace App\Filament\Clusters\Purchases;

use App\Filament\Clusters\OperationalArea;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;

class PurchasesCluster extends OperationalArea
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::ShoppingBag;

    protected static ?string $navigationLabel = 'Compras y almacén';

    protected static ?string $title = 'Compras y almacén';

    protected static ?string $slug = 'compras';

    protected static ?int $navigationSort = 20;
}
