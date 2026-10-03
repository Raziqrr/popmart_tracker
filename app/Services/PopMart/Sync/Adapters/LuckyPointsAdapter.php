<?php

namespace App\Services\PopMart\Sync\Adapters;

use App\Services\Api\PopMart\Endpoints\Activity\GetTasksByActivityAndChannel;

/**
 * Lucky points balance. UNCONFIRMED: Pop Mart's balance endpoint isn't mapped
 * yet (docs/tickets/lucky-points.md). The best lead is the daily-task
 * response we already fetch: claimed rewards go "into the Lucky Points
 * gauge", so it likely carries the gauge. The keys below are guesses;
 * popmart:capture GetTasksByActivityAndChannel --account=<id> --check shows
 * the real ones, and the field register (popmart:status) lists anything new.
 */
class LuckyPointsAdapter extends Adapter
{
    public const CONFIRMED = false;

    public static function endpoint(): string
    {
        return GetTasksByActivityAndChannel::class;
    }

    /**
     * @return array{balance: int, redeem_minimum: int|null, expiring_points: int|null, expiring_at: \Illuminate\Support\Carbon|null}
     */
    public static function map(array $json): array
    {
        $balance = self::need($json, [
            'luckyPoints', 'luckyPoint', 'points', 'userPoints', 'gauge.current', 'gauge.points', 'activity.userPoints', 'userActivity.points',
        ], 'lucky points balance');

        $minimum = self::pick($json, ['redeemMinimum', 'gauge.max', 'gauge.target', 'exchangeThreshold']);
        $expiring = self::pick($json, ['expiringPoints', 'expirePoints']);

        return [
            'balance' => max(0, (int) $balance),
            'redeem_minimum' => $minimum !== null ? (int) $minimum : null,
            'expiring_points' => $expiring !== null ? (int) $expiring : null,
            'expiring_at' => self::toTime(self::pick($json, ['expiringAt', 'expireTime'])),
        ];
    }
}
