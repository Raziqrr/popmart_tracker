<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External API Base URLs
    |--------------------------------------------------------------------------
    |
    | Base URLs for every API this app talks to, keyed by client. The
    | EndpointBuilder helper reads from here so nothing hardcodes a host,
    | and each environment (dev/qa/prod) can point at a different value.
    |
    */

    'popmart' => [
        'base_url' => env('POPMART_API_BASE_URL', 'https://prod-apac-api.popmart.com'),

        // Shared budget for every Pop Mart call (App\Services\PopMart\PopMartThrottle).
        // Background sync may use at most `background_per_minute` of it, so a
        // user's own action (lock, claim) always has room.
        'calls_per_minute' => (int) env('POPMART_CALLS_PER_MINUTE', 60),
        'background_per_minute' => (int) env('POPMART_BACKGROUND_PER_MINUTE', 40),
        // How long everything pauses after a 429 that didn't say Retry-After.
        'pause_seconds' => (int) env('POPMART_PAUSE_SECONDS', 120),

        // Write each call to popmart_calls, and new response fields to popmart_payload_fields.
        'log_calls' => (bool) env('POPMART_LOG_CALLS', true),
        'record_fields' => (bool) env('POPMART_RECORD_FIELDS', true),

        // Regions the server syncs. MY only for now.
        'areas' => explode(',', env('POPMART_AREAS', 'MY')),

        // popmart_accounts.id of the server's own Pop Mart account, used only to
        // poll POP NOW grids. It must never lock boxes (see HoldsBoxes).
        'house_account_id' => env('POPMART_HOUSE_ACCOUNT_ID'),

        'sync' => [
            // Minutes between stock checks per tier (App\Services\PopMart\Sync\SyncTier).
            'tier_minutes' => ['hot' => 1, 'normal' => 20, 'cold' => 1440],
            // A product is hot this many minutes before and after sale_start_at.
            'hot_window_minutes' => 120,
            // When the sweep sees a change in a collection, its products stay hot this long.
            'cluster_hot_minutes' => 60,
            // Stock at or below this (and above 0) raises a low_stock event.
            'low_stock' => 10,
            // Products per stock-sync run, so one run can't eat the whole budget.
            'stock_batch' => 20,
            // POP NOW sets polled per live draw product per run.
            'sets_per_product' => 3,
            // Hours of stock history the sell rate looks back over.
            'sell_rate_window_hours' => 6,
            // Seconds a viewer must wait between refreshes of the same set.
            'viewer_refresh_cooldown' => 30,
        ],
    ],

    'server' => [
        'base_url' => env('SERVER_API_BASE_URL', env('APP_URL', 'http://localhost')),
    ],

];
