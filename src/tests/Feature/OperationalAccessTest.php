<?php

namespace Tests\Feature;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WorkTimeEntries\Pages\EditWorkTimeEntry;
use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkTimeEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_worker_can_only_access_own_time_entries_and_cannot_approve_them(): void
    {
        $user = User::factory()->create(['role' => 'worker']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $own = WorkTimeEntry::factory()->create(['employee_id' => $employee->id]);
        $other = WorkTimeEntry::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(BudgetResource::canViewAny());
        $this->assertFalse(UserResource::canViewAny());
        $this->assertTrue(WorkTimeEntryResource::canEdit($own));
        $this->assertFalse(WorkTimeEntryResource::canEdit($other));
        $this->assertSame([$own->id], WorkTimeEntryResource::getEloquentQuery()->pluck('id')->all());
        Livewire::test(EditWorkTimeEntry::class, ['record' => $own->id])->assertActionHidden('confirm');
        $this->get(WorkTimeEntryResource::getUrl('edit', ['record' => $other]))->assertNotFound();
    }

    public function test_manager_sees_only_assigned_projects(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $own = Project::factory()->create(['manager_id' => $user->id]);
        $foreign = Project::factory()->create();
        $this->actingAs($user);

        $this->assertSame([$own->id], ProjectResource::getEloquentQuery()->pluck('id')->all());
        $this->assertFalse(ProjectResource::canEdit($foreign));
        $this->get(ProjectResource::getUrl('edit', ['record' => $foreign]))->assertNotFound();
    }

    public function test_inactive_accounts_cannot_access_panel(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->assertFalse($user->canAccessPanel(Filament::getCurrentPanel()));
    }
}
