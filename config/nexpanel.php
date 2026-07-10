<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MySQL admin connection (Database Manager module)
    |--------------------------------------------------------------------------
    |
    | Credentials the panel uses to manage the local MySQL/MariaDB server —
    | separate from the app's own database. Read through config (not env()
    | directly) so it keeps working when `php artisan config:cache` is used.
    |
    */

    'db_admin' => [
        'host'     => env('DB_ADMIN_HOST', '127.0.0.1'),
        'port'     => env('DB_ADMIN_PORT', 3306),
        'user'     => env('DB_ADMIN_USER', 'root'),
        'password' => env('DB_ADMIN_PASSWORD', ''),
        'socket'   => env('DB_ADMIN_SOCKET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MySQL client binaries
    |--------------------------------------------------------------------------
    |
    | Used for import, backup and recycle-bin dumps. Override with an absolute
    | path when the clients are not on the web user's PATH.
    |
    */

    'bin' => [
        'mysql'     => env('DB_ADMIN_MYSQL_BIN', 'mysql'),
        'mysqldump' => env('DB_ADMIN_MYSQLDUMP_BIN', 'mysqldump'),
    ],

];
