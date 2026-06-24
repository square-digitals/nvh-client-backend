<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            [
                'name'              => 'Alice Johnson',
                'email'             => 'alice@example.com',
                'password'          => Hash::make('password'),
                'status'            => 'active',
                'plan'              => 'starter',
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Bob Smith',
                'email'             => 'bob@example.com',
                'password'          => Hash::make('password'),
                'status'            => 'active',
                'plan'              => 'professional',
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Carol White',
                'email'             => 'carol@example.com',
                'password'          => Hash::make('password'),
                'status'            => 'suspended',
                'plan'              => 'starter',
                'suspended_reason'  => 'Non-payment of invoice.',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($clients as $data) {
            Client::firstOrCreate(['email' => $data['email']], $data);
        }
    }
}
