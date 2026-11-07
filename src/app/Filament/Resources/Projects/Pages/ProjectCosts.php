<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Widgets\ProjectCostBreakdownChart;
use App\Filament\Resources\Projects\Widgets\ProjectCostHistoryChart;
use App\Filament\Resources\Projects\Widgets\ProjectCostStats;
use App\Support\ProjectCostReport;
use App\Support\ProjectEconomics;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ProjectCosts extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.resources.projects.pages.project-costs';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        ProjectCostReport::resolve((int) $this->getRecord()->getKey());
    }

    public function getTitle(): string|Htmlable
    {
        return 'Costes · '.$this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('project')->label('Volver a la obra')->url(fn (): string => ProjectResource::getUrl('edit', ['record' => $this->getRecord()]))];
    }

    protected function getHeaderWidgets(): array
    {
        return [ProjectCostStats::class, ProjectCostBreakdownChart::class, ProjectCostHistoryChart::class];
    }

    public function getWidgetData(): array
    {
        return ['projectId' => (int) $this->getRecord()->getKey()];
    }

    protected function getViewData(): array
    {
        $project = ProjectCostReport::resolve((int) $this->getRecord()->getKey());

        return ['totals' => app(ProjectEconomics::class)->forProject($project), 'categories' => ProjectEconomics::COST_LABELS];
    }
}
