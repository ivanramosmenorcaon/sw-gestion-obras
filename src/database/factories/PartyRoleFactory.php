<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartyRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartyRole> */
class PartyRoleFactory extends Factory
{
    protected $model = PartyRole::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'role' => 'customer',
        ];
    }
}
