<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Panel;

abstract class OperationalArea extends Cluster
{
    public static function prependClusterSlug(Panel $panel, string $slug): string
    {
        return $slug;
    }

    public static function prependClusterRouteBaseName(Panel $panel, string $name): string
    {
        return $name;
    }
}
