<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | Your OpenAI API key from https://platform.openai.com/account/api-keys
    |
    */
    'api_key' => env('OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Organization ID (Optional)
    |--------------------------------------------------------------------------
    |
    | Your OpenAI organization ID, if you belong to multiple organizations
    |
    */
    'organization' => env('OPENAI_ORGANIZATION'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default OpenAI model to use for completions
    |
    */
    'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4'),

    /*
    |--------------------------------------------------------------------------
    | Default Parameters
    |--------------------------------------------------------------------------
    |
    | Default parameters for OpenAI API requests
    |
    */
    'max_tokens' => env('OPENAI_MAX_TOKENS', 2000),
    'temperature' => env('OPENAI_TEMPERATURE', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Timeout Settings
    |--------------------------------------------------------------------------
    |
    | HTTP timeout settings for API requests
    |
    */
    'timeout' => 30,
    'connect_timeout' => 10,

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Options
    |--------------------------------------------------------------------------
    |
    | Additional options for the HTTP client (useful for SSL issues on Windows)
    |
    */
    'http_options' => [
        'verify' => env('OPENAI_VERIFY_SSL', false), // Set to false for local development with SSL issues
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Models
    |--------------------------------------------------------------------------
    |
    | List of available OpenAI models for different use cases
    |
    */
    'models' => [
        'chat' => [
            'gpt-4' => 'GPT-4 (Most capable)',
            'gpt-4-turbo-preview' => 'GPT-4 Turbo (Faster)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Cost-effective)',
        ],
        'embedding' => [
            'text-embedding-ada-002' => 'Text Embedding Ada 002',
            'text-embedding-3-small' => 'Text Embedding 3 Small',
            'text-embedding-3-large' => 'Text Embedding 3 Large',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    |
    | Approximate token costs for different models (per 1K tokens)
    |
    */
    'costs' => [
        'gpt-4' => [
            'input' => 0.03,
            'output' => 0.06,
        ],
        'gpt-4-turbo-preview' => [
            'input' => 0.01,
            'output' => 0.03,
        ],
        'gpt-3.5-turbo' => [
            'input' => 0.0015,
            'output' => 0.002,
        ],
    ],
];
