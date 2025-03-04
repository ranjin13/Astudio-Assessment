<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Cache Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the API caching middleware.
    |
    */

    // Whether API caching is enabled
    'enabled' => env('API_CACHE_ENABLED', true),

    // Default cache duration in minutes
    'default_duration' => env('API_CACHE_DURATION', 15),

    // Routes with longer cache duration (in minutes)
    'long_cache_routes' => [
        'api/attributes' => env('API_CACHE_ATTRIBUTES_DURATION', 60),
    ],

    // Routes with shorter cache duration (in minutes)
    'short_cache_routes' => [
        'api/timesheets' => env('API_CACHE_TIMESHEETS_DURATION', 5),
    ],

    // Routes that should not be cached
    'excluded_routes' => [
        'api/auth',
        'api/login',
        'api/register',
        'api/password',
    ],

    // HTTP methods that should be cached
    'cacheable_methods' => ['GET'],

    // Maximum age of cache entries in days before automatic cleanup
    'max_age_days' => env('API_CACHE_MAX_AGE', 3),
]; 