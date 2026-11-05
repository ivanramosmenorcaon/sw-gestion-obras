<?php

namespace Tests\Feature;

use App\Filament\Clusters\Team\TeamCluster;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\User;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NavigationAreasTest extends TestCase
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

    public function test_main_navigation_contains_areas_instead_of_all_the_resources(): void
    {
        $this->actingAs(User::factory()->create());
        $labels = [];
        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                $labels[] = $item->getLabel();
            }
        }

        foreach (['Obras', 'Compras y almacén', 'Equipo', 'Ventas y cobros', 'Catálogo', 'Configuración'] as $label) {
            $this->assertContains($label, $labels);
        }
        $this->assertNotContains(ProjectResource::getNavigationLabel(), $labels);
        $this->assertCount(8, $labels);
        $this->assertSame('/admin/projects', parse_url(ProjectResource::getUrl('index'), PHP_URL_PATH));
        Livewire::test(ListProjects::class)->assertSuccessful()->assertSee('Preparación')->assertSee('Ejecución');
    }

    public function test_area_dropdowns_render_with_teleport_to_avoid_the_scrollable_tabs_container(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(ListProjects::class)->assertSuccessful();
        $document = new \DOMDocument;
        $document->loadHTML($page->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $panels = $xpath->query('//div[contains(@class, "fi-dropdown-panel")][div[contains(@class, "fi-cluster-navigation-items")]]');
        $this->assertGreaterThan(0, $panels->length);
        foreach ($panels as $panel) {
            $teleported = false;
            foreach ($panel->attributes as $attribute) {
                if (str_starts_with($attribute->name, 'x-float') && str_contains($attribute->name, '.teleport')) {
                    $teleported = true;
                }
            }
            $this->assertTrue($teleported, 'El menú de área debe salir del contenedor con scroll.');
        }
    }

    public function test_every_resource_has_its_own_tabler_icon(): void
    {
        $icons = [];
        foreach (Filament::getResources() as $resource) {
            $icon = $resource::getNavigationIcon();
            $this->assertInstanceOf(TablerIcon::class, $icon);
            $icons[] = $icon->value;
        }
        $this->assertCount(count($icons), array_unique($icons));
    }

    public function test_worker_only_sees_the_team_area_and_it_opens_time_entries(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'worker']));
        $labels = [];
        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                $labels[] = $item->getLabel();
            }
        }
        $this->assertContains('Equipo', $labels);
        $this->assertNotContains('Configuración', $labels);
        $this->assertNotContains('Obras', $labels);
        $this->get(TeamCluster::getUrl())->assertRedirect('/admin/work-time-entries');
    }
}
