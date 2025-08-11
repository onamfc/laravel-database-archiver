<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default storage driver that will be used
    | for archiving database records. You may set this to any of the
    | storage drivers defined in the "disks" configuration array.
    |
    */
    'default_storage' => env('DB_ARCHIVER_STORAGE', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Storage Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure the storage drivers for archival. Each driver
    | can have its own configuration options.
    |
    */
    'storage' => [
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('DB_ARCHIVER_S3_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/archives'),
            'throw' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Archive Format
    |--------------------------------------------------------------------------
    |
    | This option controls the default format for archived data.
    | Supported formats: "json", "parquet"
    |
    */
    'default_format' => env('DB_ARCHIVER_FORMAT', 'json'),

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | The number of records to process in each chunk to optimize memory usage.
    |
    */
    'chunk_size' => env('DB_ARCHIVER_CHUNK_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Archive Tables Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which tables to archive and their specific settings.
    |
    */
    'tables' => [
        'add-table-name-here' => [
            'enabled' => true,
            'criteria' => [
                'column' => 'created_at',
                'operator' => '<',
                'value' => '6 months ago', // Can be a Carbon-parseable string or closure
            ],
            'format' => 'json',
            'storage' => 's3',
            'path' => 'archives/users/{date}',
            'schedule' => 'daily', // daily, weekly, monthly, or cron expression
            'delete_after_archive' => false,
            'additional_criteria' => [
                // ['column' => 'status', 'operator' => '=', 'value' => 'inactive'],
            ],
        ],
        'add-another-table-name-here' => [
            'enabled' => true,
            'criteria' => [
                'column' => 'created_at',
                'operator' => '<',
                'value' => '3 months ago',
            ],
            'format' => 'parquet',
            'storage' => 's3',
            'path' => 'archives/logs/{date}',
            'schedule' => 'weekly',
            'delete_after_archive' => true,
        ],
        // Add more tables as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable detailed logging of archival operations.
    |
    */
    'logging' => [
        'enabled' => env('DB_ARCHIVER_LOGGING', true),
        'channel' => env('DB_ARCHIVER_LOG_CHANNEL', 'daily'),
        'level' => env('DB_ARCHIVER_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Configure notifications for archival operations.
    |
    */
    'notifications' => [
        'enabled' => env('DB_ARCHIVER_NOTIFICATIONS', false),
        'channels' => ['mail', 'slack'],
        'on_success' => true,
        'on_failure' => true,
    ],
];