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
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'phone'             => fake()->optional()->phoneNumber(),
            'company'           => fake()->optional()->company(),
            'status'            => 'active',
            'plan'              => fake()->optional()->randomElement(['starter', 'pro', 'business']),
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
