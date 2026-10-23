<?php

namespace App\Services\PopMart\Sync;

use Illuminate\Support\Carbon;

/**
 * Units sold per hour from stock snapshots (docs/tickets/fast-selling-products.md).
 * Pure, so the rules are unit tested.
 *
 * - Only decreases count as sales; a restock (increase) never cancels them out.
 * - Snapshots are written only when stock changes, so the stock between two
 *   rows is known exactly: the last row before the window is the starting
 *   point, and the window runs up to now.
 * - Masked SKUs (Pop Mart only says in/out of stock, never a count) have no rate.
 */
final class SellRate
{
    /** Shorter histories are too noisy to rank by. */
    private const MIN_HOURS = 0.5;

    /**
     * @param  list<array{checked_at: Carbon, stock: int, has_stock: bool}>  $snapshots  one SKU's rows from the
     *                                                                                   last one before $windowStart onwards, oldest first
     */
    public static function perHour(array $snapshots, Carbon $windowStart, Carbon $now): ?float
    {
        if ($snapshots === [] || self::masked($snapshots)) {
            return null;
        }

        // Where the window really starts: at $windowStart if we knew the stock then, else at the first row.
        $from = $snapshots[0]['checked_at']->lt($windowStart) ? $windowStart : $snapshots[0]['checked_at'];
        $hours = $from->diffInSeconds($now) / 3600;

        if ($hours < self::MIN_HOURS) {
            return null;
        }

        $sold = 0;
        for ($i = 1, $n = count($snapshots); $i < $n; $i++) {
            $sold += max($snapshots[$i - 1]['stock'] - $snapshots[$i]['stock'], 0);
        }

        return round($sold / $hours, 2);
    }

    /** Hours until a product's remaining stock runs out at this rate; null when it isn't selling. */
    public static function hoursToSellOut(int $remaining, ?float $ratePerHour): ?float
    {
        return $ratePerHour !== null && $ratePerHour > 0 ? round($remaining / $ratePerHour, 2) : null;
    }

    /** In stock but never a count above zero: Pop Mart masks this SKU's numbers. */
    private static function masked(array $snapshots): bool
    {
        $everInStock = false;
        foreach ($snapshots as $row) {
            if ($row['stock'] > 0) {
                return false;
            }
            $everInStock = $everInStock || $row['has_stock'];
        }

        return $everInStock;
    }
}
