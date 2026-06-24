<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'              => $this->faker->name(),
            'email'             => $this->faker->unique()->safeEmail(),
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'phone'             => $this->faker->optional()->phoneNumber(),
            'company'           => $this->faker->optional()->company(),
            'status'            => 'active',
            'plan'              => $this->faker->optional()->randomElement(['starter', 'pro', 'business']),
            'remember_token'    => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }
}
