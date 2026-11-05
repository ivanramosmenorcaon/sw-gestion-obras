<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeAbsences\Pages\ManageEmployeeAbsences;
use App\Models\EmployeeAbsence;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAbsenceResourceTest extends TestCase
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
        $this->actingAs(User::factory()->create());
    }

    public function test_list_displays_employee_description_and_dates(): void
    {
        $absence = EmployeeAbsence::factory()->create(['description' => 'Permiso personal por asunto familiar']);
        Livewire::test(ManageEmployeeAbsences::class)
            ->assertCanSeeTableRecords([$absence])
            ->assertSee($absence->employee->name)->assertSee($absence->description)
            ->assertSee($absence->starts_on->format('d/m/Y'))
            ->searchTable($absence->employee->name)->assertCanSeeTableRecords([$absence]);
    }

    public function test_draft_reference_and_description_are_saved_from_the_edit_modal(): void
    {
        $absence = EmployeeAbsence::factory()->create();
        $page = Livewire::test(ManageEmployeeAbsences::class)->mountAction(TestAction::make('edit')->table($absence));
        $page->fillForm(['reference' => 'AUS-CORREGIDA', 'description' => 'Motivo corregido'], $page->instance()->getMountedActionSchemaName())
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame('AUS-CORREGIDA', $absence->fresh()->reference);
        $this->assertSame('Motivo corregido', $absence->fresh()->description);
    }

    public function test_confirmed_absence_allows_metadata_corrections_but_keeps_approved_dates(): void
    {
        $absence = EmployeeAbsence::factory()->create();
        $absence->forceFill(['status' => 'posted'])->save();
        $date = $absence->starts_on->toDateString();
        $page = Livewire::test(ManageEmployeeAbsences::class)->mountAction(TestAction::make('edit')->table($absence));
        $page->fillForm(['reference' => 'REF-APROBADA', 'description' => 'Descripción aclarada'], $page->instance()->getMountedActionSchemaName())
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame('REF-APROBADA', $absence->fresh()->reference);
        $this->assertSame('Descripción aclarada', $absence->fresh()->description);
        $this->assertSame('posted', $absence->fresh()->status);
        $this->assertSame($date, $absence->fresh()->starts_on->toDateString());
        Livewire::test(ManageEmployeeAbsences::class)
            ->assertActionHidden(TestAction::make('post')->table($absence))
            ->assertActionHidden(TestAction::make('cancelDraft')->table($absence));

        $this->expectException(ValidationException::class);
        $absence->update(['ends_on' => today()->addDay()]);
    }
}
