<?php

namespace Tests\Feature;

use Illuminate\Support\Env;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VerifactuEnvironmentTest extends TestCase
{
    #[DataProvider('environmentValues')]
    public function test_only_the_exact_production_value_selects_production(?string $value, string $expected): void
    {
        $repository = Env::getRepository();
        $previous = $repository->get('VERIFACTU_PRODUCTION');
        try {
            if ($value === null) {
                $repository->clear('VERIFACTU_PRODUCTION');
            } else {
                $repository->set('VERIFACTU_PRODUCTION', $value);
            }
            $configuration = require config_path('billing.php');
            $this->assertSame($expected, $configuration['environment']);
        } finally {
            if ($previous === null) {
                $repository->clear('VERIFACTU_PRODUCTION');
            } else {
                $repository->set('VERIFACTU_PRODUCTION', $previous);
            }
        }
    }

    public static function environmentValues(): array
    {
        return [
            'missing' => [null, 'test'], 'empty' => ['', 'test'], 'production' => ['production', 'production'],
            'uppercase' => ['PRODUCTION', 'test'], 'boolean' => ['true', 'test'], 'number' => ['1', 'test'],
            'test' => ['test', 'test'], 'spaces' => ['production ', 'test'],
        ];
    }
}
