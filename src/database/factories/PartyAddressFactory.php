<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartyAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartyAddress> */
class PartyAddressFactory extends Factory
{
    protected $model = PartyAddress::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'label' => 'Ejemplo',
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
        ];
    }
}
