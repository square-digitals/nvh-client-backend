<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'type'      => 'wordpress',
            'name'      => fake()->words(3, true),
            'domain'    => fake()->domainName(),
            'status'    => 'pending_approval',
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status'         => 'active',
            'url'            => 'https://' . fake()->domainName(),
            'provisioned_at' => now(),
        ]);
    }

    public function terminated(): static
    {
        return $this->state(['status' => 'terminated']);
    }
}
