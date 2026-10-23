<?php

namespace App\Services\PopMart;

use App\Services\Api\PopMart\PopMartException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The one budget every Pop Mart call goes through (PopMartEndpoint::send()).
 *
 * - All calls share apis.popmart.calls_per_minute.
 * - Background calls also stay under apis.popmart.background_per_minute, so
 *   user actions always have room left.
 * - After Pop Mart answers 429, everything pauses (Retry-After, or
 *   apis.popmart.pause_seconds) instead of hammering it.
 */
class PopMartThrottle
{
    private const PAUSE_KEY = 'popmart:paused_until';

    /**
     * Take one call from the budget, or throw.
     *
     * @throws PopMartException RateLimited while paused, Throttled when the budget is used up
     */
    public function acquire(ThrottleLane $lane): void
    {
        $paused = $this->pausedFor();
        if ($paused > 0) {
            throw PopMartException::rateLimited($paused);
        }

        $limits = ['popmart:calls:all' => (int) config('apis.popmart.calls_per_minute', 60)];
        if ($lane === ThrottleLane::Background) {
            $limits['popmart:calls:background'] = (int) config('apis.popmart.background_per_minute', 40);
        }

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw PopMartException::throttled(max(1, RateLimiter::availableIn($key)));
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 60);
        }
    }

    /** Stop every Pop Mart call for a while (after a 429). */
    public function pause(?int $seconds = null): void
    {
        $seconds = max(1, $seconds ?? (int) config('apis.popmart.pause_seconds', 120));

        Cache::put(self::PAUSE_KEY, now()->addSeconds($seconds)->getTimestamp(), $seconds);
    }

    /** Seconds left on a pause; 0 when calls are allowed. */
    public function pausedFor(): int
    {
        $until = Cache::get(self::PAUSE_KEY);

        return $until ? max(0, (int) $until - now()->getTimestamp()) : 0;
    }
}
