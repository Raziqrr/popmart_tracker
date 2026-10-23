<?php

namespace App\Services\PopMart\Sync;

use App\Models\PopmartAccount;
use App\Models\PopNowBox;
use App\Models\PopNowSet;
use App\Models\Product;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Locator\Country;
use App\Services\PopMart\HouseAccount;
use App\Services\PopMart\Sync\Adapters\PopNowSetAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Live POP NOW box grids, two ways:
 *
 * - pollLive() (popmart:poll-popnow, every minute): the house account calls
 *   AssignSet for live draws people care about (viewed in the last 10
 *   minutes, watched, or in the hot tier). AssignSet releases any boxes the
 *   session holds, which is why only the house account (that never holds
 *   any) may poll this way.
 * - refreshForViewer(): a logged-in viewer's own session re-checks one set's
 *   boxes with CheckSetBoxLock, which is read-only and never touches holds.
 */
class PopNowSync
{
    private const VIEWED_MINUTES = 10;

    public function __construct(
        private HouseAccount $house,
        private WatchedProducts $watched,
    ) {}

    /** The POP NOW page and predictions call this so the poller knows what people are looking at. */
    public static function markViewed(string $productId): void
    {
        Cache::put("popnow:viewed:{$productId}", true, now()->addMinutes(self::VIEWED_MINUTES));
    }

    /** @return array{products: int, sets: int, stopped: ?string} */
    public function pollLive(): array
    {
        $report = ['products' => 0, 'sets' => 0, 'stopped' => null];
        $account = $this->house->get();

        if ($account === null) {
            $report['stopped'] = 'No house account (set POPMART_HOUSE_ACCOUNT_ID).';

            return $report;
        }

        $watched = $this->watched->ids();
        $perProduct = (int) config('apis.popmart.sync.sets_per_product', 3);

        $live = Product::query()
            ->where('business_type', 'draw')
            ->where('is_sold_out', false)
            ->where(fn ($q) => $q->whereNull('sale_start_at')->orWhere('sale_start_at', '<=', now()))
            ->get()
            ->filter(fn (Product $p) => isset($watched[$p->id]) || $p->sync_tier === SyncTier::Hot->value || Cache::has("popnow:viewed:{$p->id}"));

        try {
            $account->usingSession(function (string $cookie, Country $country) use ($live, $perProduct, &$report) {
                foreach ($live as $product) {
                    $seen = [];
                    for ($i = 0; $i < $perProduct; $i++) {
                        $endpoint = PopNowSetAdapter::assignEndpoint();
                        $json = (new $endpoint($country))->withSession($cookie)->asHouse()->data(PopNowSetAdapter::assignRequest($product->id));
                        $set = PopNowSetAdapter::mapSet($json ?? []);

                        // Pop Mart hands out the same set again: we've seen what's on offer.
                        if (isset($seen[$set['set_no']])) {
                            break;
                        }
                        $seen[$set['set_no']] = true;

                        $this->writeSet($product, $set);
                        $report['sets']++;
                    }
                    $report['products']++;
                }
            });
        } catch (PopMartException $e) {
            if (! $e->abortsBatch()) {
                throw $e;
            }
            $report['stopped'] = $e->getMessage();
        } catch (UnexpectedPayload $e) {
            Log::warning('Pop Mart POP NOW: '.$e->getMessage());
            $report['stopped'] = $e->getMessage();
        }

        return $report;
    }

    /**
     * Re-check one set's open boxes with the viewer's own session (read-only).
     * At most one refresh per set every apis.popmart.sync.viewer_refresh_cooldown seconds.
     *
     * @return array{refreshed: int, cooldown: bool}
     *
     * @throws PopMartException
     */
    public function refreshForViewer(PopNowSet $set, PopmartAccount $account): array
    {
        $cooldown = (int) config('apis.popmart.sync.viewer_refresh_cooldown', 30);

        if (! Cache::add("popnow:refresh:{$set->id}", true, $cooldown)) {
            return ['refreshed' => 0, 'cooldown' => true];
        }

        $boxes = $set->boxes()->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'sold'))->get();
        $refreshed = 0;

        $account->usingSession(function (string $cookie, Country $country) use ($set, $boxes, &$refreshed) {
            foreach ($boxes as $box) {
                $endpoint = PopNowSetAdapter::lockEndpoint();
                $lock = PopNowSetAdapter::mapLock(
                    (new $endpoint($country))->withSession($cookie)->data(PopNowSetAdapter::lockRequest($set->product_id, $set->set_no, $box->box_no)) ?? []
                );

                $box->update([
                    'is_locked' => $lock['is_locked'],
                    // Store what's left from now, so PopNowBox::lockExpiresAt() is right.
                    'lock_started_at' => $lock['is_locked'] ? now() : null,
                    'lock_duration_seconds' => $lock['is_locked'] ? $lock['lock_remaining_seconds'] : null,
                    'last_seen_at' => now(),
                ]);
                $refreshed++;
            }
        });

        $set->update(['last_seen_at' => now()]);

        return ['refreshed' => $refreshed, 'cooldown' => false];
    }

    private function writeSet(Product $product, array $data): void
    {
        DB::transaction(function () use ($product, $data) {
            $now = now();
            $set = PopNowSet::updateOrCreate(
                ['product_id' => $product->id, 'set_no' => $data['set_no']],
                ['total_boxes' => count($data['boxes']), 'last_seen_at' => $now],
            );

            $existing = $set->boxes()->get()->keyBy('box_no');

            foreach ($data['boxes'] as $box) {
                $was = $existing->get($box['box_no']);
                $newlyLocked = $box['is_locked'] && ! $was?->is_locked;

                PopNowBox::updateOrCreate(
                    ['pop_now_set_id' => $set->id, 'box_no' => $box['box_no']],
                    [
                        'position' => $box['position'],
                        'status' => $box['status'],
                        'is_locked' => $box['is_locked'],
                        // The house account never holds boxes, so any lock it sees is someone else's.
                        'locked_by_other' => $box['is_locked'],
                        'lock_started_at' => $box['is_locked'] ? ($newlyLocked ? $now : $was?->lock_started_at) : null,
                        'last_seen_at' => $now,
                    ],
                );
            }
        });
    }
}
