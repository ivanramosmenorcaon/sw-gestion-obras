<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartyRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Party> */
class PartyFactory extends Factory
{
    protected $model = Party::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'type' => 'individual',
            'legal_name' => fake()->company(),
        ];
    }

    public function customer(): static
    {
        return $this->afterCreating(fn (Party $party): PartyRole => $party->partyRoles()->create(['role' => 'customer']));
    }

    public function supplier(): static
    {
        return $this->afterCreating(fn (Party $party): PartyRole => $party->partyRoles()->create(['role' => 'supplier']));
    }

    public function contractor(): static
    {
        return $this->afterCreating(fn (Party $party): PartyRole => $party->partyRoles()->create(['role' => 'subcontractor']));
    }
}
