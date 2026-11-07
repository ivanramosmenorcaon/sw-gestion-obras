<?php

namespace App\Filament\Resources\Projects\Widgets;

use App\Support\ProjectCostReport;
use Filament\Widgets\ChartWidget;

class ProjectCostHistoryChart extends ChartWidget
{
    use InteractsWithProjectCosts;

    protected ?string $heading = 'Evolución del coste';

    protected ?string $description = 'Coste mensual y acumulado en EUR, por fecha de imputación. Las horas revertidas se descuentan de su mes original. El acumulado incluye los meses anteriores al intervalo.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '320px';

    public ?string $filter = '12';

    protected ?array $options = ['scales' => ['y' => ['title' => ['display' => true, 'text' => 'EUR']]], 'interaction' => ['mode' => 'index', 'intersect' => false]];

    protected function getData(): array
    {
        $history = app(ProjectCostReport::class)->history($this->costProject(), (int) $this->filter);

        return [
            'labels' => $history['labels'],
            'datasets' => [
                ['label' => 'Coste acumulado (EUR)', 'data' => array_map('floatval', $history['cumulative']), 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b', 'tension' => 0],
                ['label' => 'Coste del mes (EUR)', 'data' => array_map('floatval', $history['monthly']), 'borderColor' => '#3b82f6', 'backgroundColor' => '#3b82f6', 'tension' => 0],
            ],
        ];
    }

    protected function getFilters(): array
    {
        return ['6' => 'Últimos 6 meses', '12' => 'Últimos 12 meses', '24' => 'Últimos 24 meses'];
    }

    public function getChartAssistiveContent(): string
    {
        $data = $this->getCachedData();

        return implode('; ', array_map(fn (string $month, float $monthly, float $cumulative): string => $month.': mensual '.ProjectCostReport::money((string) $monthly).', acumulado '.ProjectCostReport::money((string) $cumulative), $data['labels'], $data['datasets'][1]['data'], $data['datasets'][0]['data']));
    }

    protected function getType(): string
    {
        return 'line';
    }
}
