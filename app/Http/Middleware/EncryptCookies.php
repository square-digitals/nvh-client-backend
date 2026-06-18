<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncryptCookies;

class EncryptCookies extends BaseEncryptCookies
{
    // nvh_client_token: Sanctum validates it server-side; already protected by HttpOnly + Secure + SameSite.
    // XSRF-TOKEN: must be readable by JS to attach as X-XSRF-TOKEN header.
    protected $except = [
        'nvh_client_token',
        'XSRF-TOKEN',
    ];
}
