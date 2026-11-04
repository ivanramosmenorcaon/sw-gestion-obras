<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Filament\Resources\WorkOrders\Pages\EditWorkOrder;
use App\Filament\Resources\WorkOrders\RelationManagers\WorkOrderPhasesRelationManager;
use App\Filament\Widgets\GettingStartedGuide;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkOrderPhase;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardGuideTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_authenticated_dashboard_displays_the_guide_with_workflow_links(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('filament.admin.pages.dashboard'))->assertOk()
            ->assertSee(__('onboarding.title'))->assertSee(ProjectResource::getUrl('index'))
            ->assertSee('guide-step-12')->assertSee(__('onboarding.expand'));
    }

    public function test_guide_does_not_link_to_resources_the_user_cannot_access(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::policy(Project::class, DenyGuideProjectAccess::class);
        Livewire::test(GettingStartedGuide::class)->assertSuccessful()
            ->assertSee(__('onboarding.title'))->assertDontSee(ProjectResource::getUrl('index'));
    }

    public function test_order_phase_list_links_to_the_complete_phase_page(): void
    {
        $this->actingAs(User::factory()->create());
        $phase = WorkOrderPhase::factory()->create();
        Livewire::test(WorkOrderPhasesRelationManager::class, ['ownerRecord' => $phase->workOrder, 'pageClass' => EditWorkOrder::class])
            ->assertActionVisible(TestAction::make('openPhase')->table($phase))
            ->assertSee(WorkOrderPhaseResource::getUrl('edit', ['record' => $phase]));
        Gate::policy(WorkOrderPhase::class, DenyGuidePhaseEditing::class);
        Livewire::test(WorkOrderPhasesRelationManager::class, ['ownerRecord' => $phase->workOrder, 'pageClass' => EditWorkOrder::class])
            ->assertActionHidden(TestAction::make('openPhase')->table($phase));
    }
}

class DenyGuideProjectAccess
{
    public function viewAny(User $user): bool
    {
        return false;
    }
}

class DenyGuidePhaseEditing
{
    public function update(User $user, WorkOrderPhase $phase): bool
    {
        return false;
    }
}
