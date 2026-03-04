<?php

return [
    'signup_enabled' => (bool) env('SIGNUP_ENABLED', false),
    'signup_token_bundle' => (int) env('SIGNUP_TOKEN_BUNDLE', 0),
    'referal_enabled' => (bool) env('REFERAL_ENABLED', false),
    'referal_frequency' => (int) env('REFERAL_FREQUENCY',10),
    'referal_token_bundle' => (int) env('REFERAL_TOKEN_BUNDLE', 0),
];
