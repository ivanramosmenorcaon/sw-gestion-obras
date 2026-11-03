<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartyCustomerSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartyCustomerSetting> */
class PartyCustomerSettingFactory extends Factory
{
    protected $model = PartyCustomerSetting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
        ];
    }
}
