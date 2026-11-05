<?php

namespace App\Filament\Clusters\Finance;

use App\Filament\Clusters\OperationalArea;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;

class FinanceCluster extends OperationalArea
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Wallet;

    protected static ?string $navigationLabel = 'Ventas y cobros';

    protected static ?string $title = 'Ventas y cobros';

    protected static ?string $slug = 'ventas-cobros';

    protected static ?int $navigationSort = 40;
}
