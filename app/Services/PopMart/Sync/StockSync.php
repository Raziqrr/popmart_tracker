<?php

namespace App\Services\PopMart\Sync;

use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\Sku;
use App\Models\StockSnapshot;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\Adapters\SkuStockAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tiered stock polling (popmart:sync-stock, every minute). Each run checks
 * the products whose next_stock_check_at is due, oldest first, at most
 * apis.popmart.sync.stock_batch of them.
 *
 * - A stock_snapshots row is written only when a SKU's stock changed;
 *   skus.last_checked_at records every check.
 * - Changes become product_events (restocked, sold_out, low_stock).
 * - Afterwards the product's tier (SyncTier) is decided again, which sets
 *   when it's next due.
 */
class StockSync
{
    public function __construct(
        private ProductChangeDetector $detector,
        private WatchedProducts $watched,
    ) {}

    /** @return array{checked: int, snapshots: int, events: int, skipped: int, stopped: ?string} */
    public function runDue(?int $batch = null): array
    {
        $report = ['checked' => 0, 'snapshots' => 0, 'events' => 0, 'skipped' => 0, 'stopped' => null];
        $now = now();

        $products = Product::query()
            ->where(fn ($q) => $q->whereNull('next_stock_check_at')->orWhere('next_stock_check_at', '<=', $now))
            ->orderByRaw('next_stock_check_at is not null')
            ->orderBy('next_stock_check_at')
            ->limit($batch ?? (int) config('apis.popmart.sync.stock_batch', 20))
            ->with('skus.latestStockSnapshot')
            ->get();

        $watched = $this->watched->ids();

        foreach ($products as $product) {
            try {
                $this->check($product, isset($watched[$product->id]), $report);
            } catch (PopMartException $e) {
                if ($e->abortsBatch()) {
                    $report['stopped'] = $e->getMessage();
                    break;
                }
                Log::warning("Pop Mart stock: check failed for {$product->id}", ['error' => $e->getMessage()]);
                $this->reschedule($product, isset($watched[$product->id]), $report);
            } catch (UnexpectedPayload $e) {
                Log::warning('Pop Mart stock: '.$e->getMessage());
                $this->reschedule($product, isset($watched[$product->id]), $report);
            }
        }

        return $report;
    }

    private function check(Product $product, bool $watched, array &$report): void
    {
        $country = Country::tryFrom($product->area_codes[0] ?? 'MY') ?? Country::Malaysia;
        $endpoint = SkuStockAdapter::endpoint();
        $stock = SkuStockAdapter::map((new $endpoint($country))->data(SkuStockAdapter::request($product->id)));
        $now = now();
        $lowStock = (int) config('apis.popmart.sync.low_stock', 10);

        DB::transaction(function () use ($product, $stock, $now, $lowStock, &$report) {
            $checked = [];

            foreach ($product->skus as $sku) {
                if (! isset($stock[$sku->id])) {
                    continue;
                }

                $latest = $sku->latestStockSnapshot;
                $before = $latest ? ['stock' => $latest->stock, 'has_stock' => $latest->has_stock] : null;
                $after = $stock[$sku->id];
                $checked[] = $sku->id;

                if (! ProductChangeDetector::stockChanged($before, $after)) {
                    continue;
                }

                $snapshot = StockSnapshot::create(['sku_id' => $sku->id, ...$after, 'checked_at' => $now]);
                $sku->setRelation('latestStockSnapshot', $snapshot);
                $report['snapshots']++;

                foreach ($this->detector->stockChanges($before, $after, $lowStock) as $event) {
                    ProductEvent::create([
                        'product_id' => $product->id,
                        'sku_id' => $sku->id,
                        'type' => $event['type'],
                        'from' => $event['from'],
                        'to' => $event['to'],
                        'occurred_at' => $now,
                    ]);
                    $report['events']++;
                }
            }

            Sku::whereIn('id', $checked)->update(['last_checked_at' => $now]);
        });

        $report['checked']++;
        $this->reschedule($product, $watched, $report, countSkipped: false);
    }

    private function reschedule(Product $product, bool $watched, array &$report, bool $countSkipped = true): void
    {
        $now = now();
        $lastChange = $product->skus->map(fn (Sku $sku) => $sku->latestStockSnapshot?->checked_at)->filter()->max();

        $tier = SyncTier::decide(
            watched: $watched,
            preorder: $product->sale_mode === 'preorder',
            saleStartAt: $product->sale_start_at,
            hotUntil: $product->hot_until,
            soldOut: (bool) $product->is_sold_out,
            lastStockChangeAt: $lastChange,
            lastSeenAt: $product->last_seen_at,
            now: $now,
        );

        $product->update(['sync_tier' => $tier->value, 'next_stock_check_at' => $tier->nextCheck($now)]);

        if ($countSkipped) {
            $report['skipped']++;
        }
    }
}
