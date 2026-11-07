<?php

namespace App\Filament\Resources\Projects\Widgets;

use App\Support\ProjectCostReport;
use App\Support\ProjectEconomics;
use Filament\Widgets\ChartWidget;

class ProjectCostBreakdownChart extends ChartWidget
{
    use InteractsWithProjectCosts;

    protected ?string $heading = 'En qué se ha gastado';

    protected ?string $description = 'Coste neto confirmado por categoría, en EUR. Las compras ligadas a consumos o subcontratas no se suman otra vez.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '320px';

    protected ?array $options = ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'EUR']]]];

    protected function getData(): array
    {
        $totals = app(ProjectEconomics::class)->forProject($this->costProject());

        return [
            'labels' => array_values(ProjectEconomics::COST_LABELS),
            'datasets' => [['label' => 'Coste neto (EUR)', 'data' => array_map(fn (string $key): float => (float) $totals[$key], array_keys(ProjectEconomics::COST_LABELS)), 'backgroundColor' => ['#f59e0b', '#3b82f6', '#8b5cf6', '#f43f5e', '#14b8a6']]],
        ];
    }

    public function getChartAssistiveContent(): string
    {
        $data = $this->getCachedData();

        return implode('; ', array_map(fn (string $label, float $amount): string => $label.': '.ProjectCostReport::money((string) $amount), $data['labels'], $data['datasets'][0]['data']));
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
