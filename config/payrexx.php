<?php

return [
    'enabled' => (bool) env('PAYREXX_ENABLED', false),
    'instance' => env('PAYREXX_INSTANCE', ''),
    'api_secret' => env('PAYREXX_API_SECRET', ''),
    'currency' => env('PAYREXX_CURRENCY', 'CHF'),
    'gateway_validity_minutes' => (int) env('PAYREXX_GATEWAY_VALIDITY', 30),
];
