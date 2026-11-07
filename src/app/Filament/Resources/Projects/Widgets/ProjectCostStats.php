<?php

namespace App\Filament\Resources\Projects\Widgets;

use App\Support\ProjectCostReport;
use App\Support\ProjectEconomics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectCostStats extends StatsOverviewWidget
{
    use InteractsWithProjectCosts;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $totals = app(ProjectEconomics::class)->forProject($this->costProject());

        return [
            Stat::make('Coste real incurrido', ProjectCostReport::money($totals['actual']))->description('Solo imputaciones confirmadas, con devoluciones y correcciones')->color('danger'),
            Stat::make('Venta aceptada', ProjectCostReport::money($totals['accepted']))->description('Presupuestos aceptados, sin impuestos')->color('success'),
            Stat::make('Contratado y pedido', ProjectCostReport::money($totals['committed']))->description('Importe nominal; puede incluir costes ya incurridos')->color('warning'),
            Stat::make('Cobrado', ProjectCostReport::money($totals['received']))->description('Cobros y anticipos menos devoluciones')->color('success'),
            Stat::make('Pagado', ProjectCostReport::money($totals['paid']))->description('Pagos y anticipos menos devoluciones')->color('danger'),
            Stat::make('Flujo de caja neto', ProjectCostReport::money($totals['cash']))->description('Cobrado menos pagado; no equivale al beneficio')->color(str_starts_with($totals['cash'], '-') ? 'danger' : 'success'),
        ];
    }
}
