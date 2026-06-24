<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClientSeeder extends Seeder
{
    // Fixed UUIDs shared with the admin backend seeder so invoice syncs resolve correctly.
    private const CLIENTS = [
        [
            'id'                => 'a0000000-0000-4000-8000-000000000001',
            'name'              => 'Alice Johnson',
            'email'             => 'alice@example.com',
            'password'          => 'password',
            'status'            => 'active',
            'plan'              => 'starter',
            'email_verified_at' => true,
        ],
        [
            'id'                => 'b0000000-0000-4000-8000-000000000002',
            'name'              => 'Bob Smith',
            'email'             => 'bob@example.com',
            'password'          => 'password',
            'status'            => 'active',
            'plan'              => 'professional',
            'email_verified_at' => true,
        ],
        [
            'id'               => 'c0000000-0000-4000-8000-000000000003',
            'name'             => 'Carol White',
            'email'            => 'carol@example.com',
            'password'         => 'password',
            'status'           => 'suspended',
            'plan'             => 'starter',
            'suspended_reason' => 'Non-payment of invoice.',
            'email_verified_at' => true,
        ],
    ];

    public function run(): void
    {
        foreach (self::CLIENTS as $data) {
            Client::where('email', $data['email'])->delete();

            Client::create([
                'id'                => $data['id'],
                'name'              => $data['name'],
                'email'             => $data['email'],
                'password'          => Hash::make($data['password']),
                'status'            => $data['status'],
                'plan'              => $data['plan'],
                'suspended_reason'  => $data['suspended_reason'] ?? null,
                'email_verified_at' => now(),
            ]);
        }
    }
}
