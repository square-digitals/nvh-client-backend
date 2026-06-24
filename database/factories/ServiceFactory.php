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
            'name'      => $this->faker->words(3, true),
            'domain'    => $this->faker->domainName(),
            'status'    => 'pending_approval',
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status'         => 'active',
            'url'            => 'https://' . $this->faker->domainName(),
            'provisioned_at' => now(),
        ]);
    }

    public function terminated(): static
    {
        return $this->state(['status' => 'terminated']);
    }
}
