<?php

namespace App\Services\Alerts;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\Sku;
use App\Models\User;
use App\Models\WishlistItem;
use App\Notifications\ProductAlert;
use App\Notifications\ProductAlertDigest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Turns new product_events into alerts for watchers (popmart:dispatch-alerts,
 * every minute). Every event is handled once (product_events.alerted_at).
 *
 * - Events older than STALE_MINUTES are marked handled without alerting
 *   (e.g. after downtime, or the first sweep of a big collection).
 * - Each user only gets the event types they chose (AlertPreferences).
 * - The same kind of alert for the same product is sent at most once per
 *   cooldown (COOLDOWN_MINUTES), so a stock count dropping 20 → 15 → 9
 *   doesn't buzz three times.
 * - More than DIGEST_AFTER alerts for one user in one run become one digest.
 */
class AlertDispatcher
{
    private const BATCH = 500;

    private const STALE_MINUTES = 60;

    private const DIGEST_AFTER = 5;

    private const COOLDOWN_MINUTES = [
        'low_stock' => 360,
        'price_drop' => 720,
        'sale_time_changed' => 60,
        'default' => 15,
    ];

    /** @return array{events: int, alerts: int, digests: int, stale: int} */
    public function run(): array
    {
        $report = ['events' => 0, 'alerts' => 0, 'digests' => 0, 'stale' => 0];
        $now = now();

        $events = ProductEvent::whereNull('alerted_at')->orderBy('id')->limit(self::BATCH)->with('product')->get();
        if ($events->isEmpty()) {
            return $report;
        }

        [$fresh, $stale] = $events->partition(fn (ProductEvent $e) => $e->occurred_at->gte($now->copy()->subMinutes(self::STALE_MINUTES)));
        $report['stale'] = $stale->count();

        $productIds = $fresh->pluck('product_id')->unique()->values();
        $skuProduct = Sku::whereIn('product_id', $productIds)->pluck('product_id', 'id')->all();
        $collectionIds = Product::whereIn('id', $productIds)->whereNotNull('collection_id')->pluck('collection_id')->unique()->values();
        [$watches, $rows] = $this->watches($productIds->all(), array_keys($skuProduct), $collectionIds->all());

        /** @var array<int, list<ProductEvent>> $byUser */
        $byUser = [];
        foreach ($fresh as $event) {
            $target = [
                'type' => $event->type,
                'product_id' => $event->product_id,
                'sku_id' => $event->sku_id,
                'collection_id' => $event->product?->collection_id,
            ];

            foreach (AlertTargets::users($target, $watches, $skuProduct) as $userId) {
                $byUser[$userId][] = $event;
            }
        }

        $users = User::whereIn('id', array_keys($byUser))->with('setting')->get()->keyBy('id');

        foreach ($byUser as $userId => $userEvents) {
            $user = $users->get($userId);
            if ($user === null) {
                continue;
            }

            $preferences = AlertPreferences::for($user);
            $toSend = collect($userEvents)
                ->filter(fn (ProductEvent $e) => $preferences->wants($e->type))
                ->filter(fn (ProductEvent $e) => $this->claimCooldown($userId, $e))
                ->values();

            if ($toSend->isEmpty()) {
                continue;
            }

            if ($toSend->count() > self::DIGEST_AFTER) {
                Notification::send($user, new ProductAlertDigest($toSend));
                $report['digests']++;
            } else {
                $toSend->each(fn (ProductEvent $e) => Notification::send($user, new ProductAlert($e)));
                $report['alerts'] += $toSend->count();
            }

            $this->markNotified($rows, $userId, $toSend, $skuProduct, $now);
        }

        ProductEvent::whereIn('id', $events->pluck('id'))->update(['alerted_at' => $now]);
        $report['events'] = $events->count();

        return $report;
    }

    /**
     * Wishlist rows for these products, their SKUs and their collections.
     *
     * @return array{0: list<array{user_id: int, kind: string, id: string}>, 1: \Illuminate\Support\Collection<int, WishlistItem>}
     */
    private function watches(array $productIds, array $skuIds, array $collectionIds): array
    {
        $kinds = [
            (new Product)->getMorphClass() => ['product', $productIds],
            (new Sku)->getMorphClass() => ['sku', $skuIds],
            (new Collection)->getMorphClass() => ['collection', $collectionIds],
        ];

        $rows = WishlistItem::query()
            ->where(function ($query) use ($kinds) {
                foreach ($kinds as $type => [, $ids]) {
                    $query->orWhere(fn ($q) => $q->where('watchable_type', $type)->whereIn('watchable_id', $ids));
                }
            })
            ->get();

        $watches = $rows->map(fn (WishlistItem $row) => [
            'user_id' => (int) $row->user_id,
            'kind' => $kinds[$row->watchable_type][0],
            'id' => (string) $row->watchable_id,
        ])->all();

        return [$watches, $rows];
    }

    /** Whether this user may get this kind of alert for this product now; claims the slot if so. */
    private function claimCooldown(int $userId, ProductEvent $event): bool
    {
        $minutes = self::COOLDOWN_MINUTES[$event->type->value] ?? self::COOLDOWN_MINUTES['default'];
        $scope = $event->sku_id ?? $event->product_id;

        return Cache::add("alerts:cooldown:{$userId}:{$scope}:{$event->type->value}", true, now()->addMinutes($minutes));
    }

    private function markNotified($rows, int $userId, $sent, array $skuProduct, $now): void
    {
        $productIds = $sent->pluck('product_id')->unique()->all();

        $rows->filter(fn (WishlistItem $row) => (int) $row->user_id === $userId)
            ->filter(fn (WishlistItem $row) => in_array($row->watchable_id, $productIds, true)
                || in_array($skuProduct[$row->watchable_id] ?? null, $productIds, true)
                || $sent->contains(fn (ProductEvent $e) => $e->product?->collection_id === $row->watchable_id))
            ->each(fn (WishlistItem $row) => $row->update(['notified_at' => $now]));
    }
}
