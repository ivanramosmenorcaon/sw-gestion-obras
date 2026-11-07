<?php

namespace App\Support;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

final class ProjectCostReport
{
    public static function canView(Project $project): bool
    {
        $user = OperationalAccess::user();

        return $user?->is_active && OperationalAccess::canSeeCosts()
            && ProjectResource::canView($project)
            && ($user->role !== 'manager' || $project->manager_id === $user->id);
    }

    public static function resolve(int $projectId): Project
    {
        $project = ProjectResource::getEloquentQuery()->findOrFail($projectId);
        abort_unless(self::canView($project), 403);

        return $project;
    }

    public static function money(string $amount): string
    {
        return BudgetEditor::formatAmount($amount).' €';
    }

    /** @return array{labels: list<string>, monthly: list<string>, cumulative: list<string>, opening: string} */
    public function history(Project $project, int $months = 12): array
    {
        $months = in_array($months, [6, 12, 24], true) ? $months : 12;
        $totals = [];
        foreach (app(ProjectEconomics::class)->costSources($project) as $source) {
            foreach ($source['query']->select([$source['date'].' as cost_date', $source['amount'].' as cost_amount'])->lazy() as $record) {
                $month = CarbonImmutable::parse($record->getAttribute('cost_date'), 'Europe/Madrid')->format('Y-m');
                $totals[$month] = ($totals[$month] ?? BigDecimal::of(0))->plus((string) $record->getAttribute('cost_amount'));
            }
        }
        ksort($totals);
        $end = max(CarbonImmutable::now('Europe/Madrid')->format('Y-m'), array_key_last($totals) ?? '0000-00');
        $start = CarbonImmutable::parse($end.'-01', 'Europe/Madrid')->subMonths($months - 1);
        $running = BigDecimal::of(0);
        foreach ($totals as $month => $amount) {
            if ($month < $start->format('Y-m')) {
                $running = $running->plus($amount);
            }
        }
        $result = ['labels' => [], 'monthly' => [], 'cumulative' => [], 'opening' => InvoiceCalculator::money($running)];
        for ($index = 0; $index < $months; $index++) {
            $month = $start->addMonths($index);
            $amount = $totals[$month->format('Y-m')] ?? BigDecimal::of(0);
            $running = $running->plus($amount);
            $result['labels'][] = $month->format('m/Y');
            $result['monthly'][] = InvoiceCalculator::money($amount);
            $result['cumulative'][] = InvoiceCalculator::money($running);
        }

        return $result;
    }
}
