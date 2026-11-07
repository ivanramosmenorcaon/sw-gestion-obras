<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProjectItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command?->info('Las partidas se crean desde una obra, una medición o una oferta aceptada. No hay datos maestros que cargar.');
    }
}
