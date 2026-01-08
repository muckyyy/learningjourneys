<?php

return [
    'asset_disk' => env('CERTIFICATE_ASSET_DISK', env('FILESYSTEM_DISK', env('FILESYSTEM_DRIVER', 'public'))),
    'asset_prefix' => trim(env('CERTIFICATE_ASSET_PREFIX', 'certificateassets'), '/'),
    'asset_url_ttl' => (int) env('CERTIFICATE_ASSET_URL_TTL', 3600),
    'designer' => [
        'max_upload_size' => env('CERTIFICATE_ASSET_MAX_SIZE', 5 * 1024), // kilobytes
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
    ],
];
