<?php

namespace App\Filament\Resources\Projects\Widgets;

use App\Models\Project;
use App\Support\OperationalAccess;
use App\Support\ProjectCostReport;
use Livewire\Attributes\Locked;

trait InteractsWithProjectCosts
{
    #[Locked]
    public int $projectId;

    public static function canView(): bool
    {
        return OperationalAccess::user()?->is_active && OperationalAccess::canSeeCosts();
    }

    public function hydrateInteractsWithProjectCosts(): void
    {
        $this->costProject();
    }

    protected function costProject(): Project
    {
        return ProjectCostReport::resolve($this->projectId);
    }
}
