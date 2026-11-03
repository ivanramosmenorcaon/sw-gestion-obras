<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartyContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartyContact> */
class PartyContactFactory extends Factory
{
    protected $model = PartyContact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'name' => fake()->words(3, true),
        ];
    }
}
