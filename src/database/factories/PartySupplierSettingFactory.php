<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartySupplierSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartySupplierSetting> */
class PartySupplierSettingFactory extends Factory
{
    protected $model = PartySupplierSetting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
        ];
    }
}
