<?php

return [
    'default_currency' => env('TOKENS_DEFAULT_CURRENCY', 'USD'),
    'default_expiration_days' => (int) env('TOKENS_DEFAULT_EXPIRATION_DAYS', 365),

    'virtual_vendor' => [
        'enabled' => (bool) env('TOKENS_VIRTUAL_VENDOR_ENABLED', false),
        'provider_name' => env('TOKENS_VIRTUAL_VENDOR_NAME', 'Virtual Vendor'),
    ],
];
