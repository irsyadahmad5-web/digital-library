<?php

return [
    'allowed_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('SECURITY_ALLOWED_HOSTS', '')),
    ))),

    'enforce_host' => (bool) env(
        'SECURITY_ENFORCE_HOST',
        env('APP_ENV') === 'production',
    ),

    'csp_enabled' => (bool) env('SECURITY_CSP_ENABLED', true),

    'hsts_enabled' => (bool) env(
        'SECURITY_HSTS_ENABLED',
        env('APP_ENV') === 'production',
    ),

    'hsts_max_age' => max(
        300,
        (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    ),

    'hsts_include_subdomains' => (bool) env(
        'SECURITY_HSTS_INCLUDE_SUBDOMAINS',
        false,
    ),

    'hsts_preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
];
