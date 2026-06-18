<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Inbound webhook secret — validates calls from admin backend
    'internal' => [
        'secret' => env('INTERNAL_SECRET'),
    ],

    // Outbound calls to admin backend
    'nvh_admin' => [
        'base_url' => env('NVH_ADMIN_BASE_URL'),
        'secret'   => env('NVH_ADMIN_INTERNAL_SECRET'),
    ],

    // Paystack
    'paystack' => [
        'secret_key'     => env('PAYSTACK_SECRET_KEY'),
        'public_key'     => env('PAYSTACK_PUBLIC_KEY'),
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
    ],

];
