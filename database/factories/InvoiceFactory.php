<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id'    => Client::factory(),
            'external_id'  => $this->faker->unique()->uuid(),
            'amount'       => $this->faker->randomFloat(2, 10, 500),
            'currency'     => 'NGN',
            'status'       => 'unpaid',
            'due_date'     => now()->addDays(30)->toDateString(),
            'paid_at'      => null,
            'period_start' => now()->toDateString(),
            'period_end'   => now()->addMonth()->toDateString(),
            'synced_at'    => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status'  => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status'   => 'overdue',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);
    }
}
