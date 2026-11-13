<?php

return [
    'name' => env('APP_NAME', 'Koperasi One'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'locale' => env('APP_LOCALE', 'id'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'id_ID'),
    'cipher' => 'AES-256-CBC',
    // Akun admin awal untuk db:seed (dibaca lewat config agar tetap jalan saat config di-cache).
    'admin_email' => env('ADMIN_EMAIL', 'admin@koperasi.test'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'key' => env('APP_KEY'),
    'previous_keys' => [...array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', '')))],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
