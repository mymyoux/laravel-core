<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'api' => 
    [
        'url'=>env('APP_URL', 'http://localhost'),
    ],
    'app'=>
    [
        'env' => env('APP_ENV', 'production'),
    ]
];
