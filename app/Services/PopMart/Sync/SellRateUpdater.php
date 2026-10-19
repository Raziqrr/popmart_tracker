<?php

namespace App\Services\PopMart\Sync;

use App\Models\Product;
use App\Models\Sku;
use App\Models\StockSnapshot;
use Illuminate\Support\Carbon;

/**
 * Refreshes products.sell_rate_per_hour and hours_to_sell_out
 * (popmart:sell-rates, every 10 minutes) over a rolling window
 * (apis.popmart.sync.sell_rate_window_hours).
 *
 * Only products whose stock moved in the window, or that had a rate before
 * (so a product that stopped selling drops back to 0), are recomputed.
 * Pre-orders are skipped: their "stock" is a quota, not units on a shelf.
 */
class SellRateUpdater
{
    /** @return array{updated: int} */
    public function run(): array
    {
        $now = now();
        $windowStart = $now->copy()->subHours((int) config('apis.popmart.sync.sell_rate_window_hours', 6));

        $movedProductIds = StockSnapshot::query()
            ->where('checked_at', '>=', $windowStart)
            ->join('skus', 'skus.id', '=', 'stock_snapshots.sku_id')
            ->distinct()
            ->pluck('skus.product_id');

        $products = Product::query()
            ->where('sale_mode', '!=', 'preorder')
            ->where(fn ($q) => $q->whereIn('id', $movedProductIds)->orWhereNotNull('sell_rate_per_hour'))
            ->with('skus')
            ->get();

        foreach ($products as $product) {
            $rates = [];
            $remaining = 0;

            foreach ($product->skus as $sku) {
                $rows = $this->rows($sku, $windowStart);
                $rate = SellRate::perHour($rows, $windowStart, $now);

                if ($rate !== null) {
                    $rates[] = $rate;
                    $remaining += end($rows)['stock'];
                }
            }

            $rate = $rates === [] ? null : round(array_sum($rates), 2);

            $product->update([
                'sell_rate_per_hour' => $rate,
                'hours_to_sell_out' => SellRate::hoursToSellOut($remaining, $rate),
                'sell_rate_computed_at' => $now,
            ]);
        }

        return ['updated' => $products->count()];
    }

    /** @return list<array{checked_at: Carbon, stock: int, has_stock: bool}> */
    private function rows(Sku $sku, $windowStart): array
    {
        $before = $sku->stockSnapshots()->where('checked_at', '<', $windowStart)->latest('checked_at')->first();
        $within = $sku->stockSnapshots()->where('checked_at', '>=', $windowStart)->orderBy('checked_at')->get();

        return collect([$before])->filter()->concat($within)
            ->map(fn (StockSnapshot $s) => ['checked_at' => $s->checked_at, 'stock' => $s->stock, 'has_stock' => $s->has_stock])
            ->values()
            ->all();
    }
}
