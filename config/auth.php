<?php

use App\Models\Client;

return [

    'defaults' => [
        'guard'     => 'sanctum',
        'passwords' => 'clients',
    ],

    'guards' => [
        'sanctum' => [
            'driver'   => 'sanctum',
            'provider' => 'clients',
        ],
    ],

    'providers' => [
        'clients' => [
            'driver' => 'eloquent',
            'model'  => Client::class,
        ],
    ],

    'passwords' => [
        'clients' => [
            'provider' => 'clients',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
