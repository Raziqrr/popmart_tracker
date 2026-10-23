<?php

namespace App\Services\PopMart\Sync;

use Illuminate\Support\Carbon;

/** How often a product's stock is checked. Minutes per tier: apis.popmart.sync.tier_minutes. */
enum SyncTier: string
{
    case Hot = 'hot';
    case Normal = 'normal';
    case Cold = 'cold';

    /**
     * Hot: someone pins or watches it, it's a pre-order, its sale starts or just
     * started (within hot_window_minutes), or something changed in its
     * collection recently (hot_until). Cold: sold out and quiet for a week, or
     * Pop Mart stopped listing it. Everything else: normal.
     */
    public static function decide(
        bool $watched,
        bool $preorder,
        ?Carbon $saleStartAt,
        ?Carbon $hotUntil,
        bool $soldOut,
        ?Carbon $lastStockChangeAt,
        ?Carbon $lastSeenAt,
        Carbon $now,
    ): self {
        $window = (int) config('apis.popmart.sync.hot_window_minutes', 120);

        if ($watched || $preorder || ($hotUntil !== null && $hotUntil->isAfter($now))) {
            return self::Hot;
        }

        if ($saleStartAt !== null && abs($now->diffInMinutes($saleStartAt, false)) <= $window) {
            return self::Hot;
        }

        $quietForAWeek = $lastStockChangeAt === null || $lastStockChangeAt->lt($now->copy()->subWeek());
        $delisted = $lastSeenAt !== null && $lastSeenAt->lt($now->copy()->subWeek());

        if (($soldOut && $quietForAWeek) || $delisted) {
            return self::Cold;
        }

        return self::Normal;
    }

    public function nextCheck(Carbon $now): Carbon
    {
        $minutes = (int) (config('apis.popmart.sync.tier_minutes')[$this->value] ?? 20);

        return $now->copy()->addMinutes($minutes);
    }
}
