<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Jules Tier
    |--------------------------------------------------------------------------
    |
    | Supported tiers: 'free', 'pro', 'ultra', 'custom'
    |   - free:  15 daily tasks (rolling 24h),  3 concurrent tasks
    |   - pro:   100 daily tasks (rolling 24h), 15 concurrent tasks
    |   - ultra: 300 daily tasks (rolling 24h), 60 concurrent tasks
    |
    */

    'tier' => env('JULES_TIER', 'free'),

    /*
    |--------------------------------------------------------------------------
    | Custom Overrides
    |--------------------------------------------------------------------------
    |
    | When set, these override the tier defaults.
    |
    */

    'daily_limit' => env('JULES_DAILY_LIMIT') ? (int) env('JULES_DAILY_LIMIT') : null,

    'concurrency_limit' => env('JULES_CONCURRENCY_LIMIT') ? (int) env('JULES_CONCURRENCY_LIMIT') : null,

    /*
    |--------------------------------------------------------------------------
    | Task Timeout (Minutes)
    |--------------------------------------------------------------------------
    |
    | If a task remains in 'dispatched' or 'running' status longer than this
    | threshold without completing, it is marked as timed_out to free concurrency.
    |
    */

    'task_timeout_minutes' => (int) env('JULES_TASK_TIMEOUT_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Queue Persistence Path
    |--------------------------------------------------------------------------
    |
    | Location where queue state and rolling task execution records are stored.
    |
    */

    'storage_path' => storage_path('app/jules/queue.json'),

    /*
    |--------------------------------------------------------------------------
    | Jules API Key
    |--------------------------------------------------------------------------
    */

    'api_key' => env('JULES_API_KEY'),

];
