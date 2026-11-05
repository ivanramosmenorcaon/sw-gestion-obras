<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Panel;

abstract class OperationalArea extends Cluster
{
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function prependClusterSlug(Panel $panel, string $slug): string
    {
        return $slug;
    }

    public static function prependClusterRouteBaseName(Panel $panel, string $name): string
    {
        return $name;
    }
}
