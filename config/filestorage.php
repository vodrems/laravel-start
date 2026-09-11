<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Uploaded files are kept on a private disk (not reachable from the web)
    | and are served only through the download route.
    |
    */

    'disk' => env('FILE_STORAGE_DISK', 'local'),

    'directory' => 'uploads',

    /*
    |--------------------------------------------------------------------------
    | Upload Rules
    |--------------------------------------------------------------------------
    */

    'max_size_kb' => (int) env('FILE_MAX_SIZE_KB', 10240),

    'allowed_extensions' => ['pdf', 'docx'],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Files are removed automatically by the "files:purge-expired" command
    | once this many hours have passed since the upload.
    |
    */

    'ttl_hours' => (int) env('FILE_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Deletion Notifications
    |--------------------------------------------------------------------------
    |
    | Every deletion (manual or automatic) publishes a message to RabbitMQ.
    | The queue worker consumes it and emails the address below.
    |
    */

    'notifications' => [
        'email' => env('NOTIFICATION_EMAIL', 'admin@example.com'),
        'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'rabbitmq'),
        'queue' => env('NOTIFICATION_QUEUE', 'notifications'),
    ],

];
