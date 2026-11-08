<?php

namespace Tests\Unit;

use App\Filament\Support\ActionIconsHelper;
use PHPUnit\Framework\TestCase;

class ActionIconsHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Simular config
        if (! function_exists('config')) {
            function config($key, $default = null)
            {
                $configs = [
                    'action-icons.actions.edit' => ['icon' => 'tabler-pencil', 'tooltip' => 'Editar registro'],
                    'action-icons.actions.delete' => ['icon' => 'tabler-trash', 'tooltip' => 'Eliminar registro'],
                ];

                return $configs[$key] ?? $default;
            }
        }
    }

    public function test_get_icon(): void
    {
        $icon = ActionIconsHelper::getIcon('edit');
        $this->assertEquals('tabler-pencil', $icon);
    }

    public function test_get_tooltip(): void
    {
        $tooltip = ActionIconsHelper::getTooltip('edit');
        $this->assertEquals('Editar registro', $tooltip);
    }

    public function test_get_icon_returns_null_for_non_existent_action(): void
    {
        $icon = ActionIconsHelper::getIcon('non_existent');
        $this->assertNull($icon);
    }

    public function test_get_all_icons(): void
    {
        $icons = ActionIconsHelper::getAllIcons();
        $this->assertIsArray($icons);
    }
}
