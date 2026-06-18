<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(trim((string) $value));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain)[0];

        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            $isPublic = filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if (! $isPublic) {
                $fail('The :attribute must be a public domain, not a private IP address.');
                return;
            }
            $fail('The :attribute must be a domain name, not an IP address.');
            return;
        }

        $blockedHosts = ['localhost', 'host.docker.internal'];
        if (in_array($domain, $blockedHosts, true)) {
            $fail('The :attribute cannot be an internal hostname.');
            return;
        }

        $internalTlds = ['.local', '.internal', '.localhost', '.test', '.example', '.invalid'];
        foreach ($internalTlds as $tld) {
            if (str_ends_with($domain, $tld)) {
                $fail('The :attribute must use a public domain extension.');
                return;
            }
        }

        if (! str_contains($domain, '.')) {
            $fail('The :attribute must be a fully qualified domain name (e.g. example.com).');
        }
    }
}
