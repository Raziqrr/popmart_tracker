<?php

namespace App\Services\PopMart\Sync;

use Illuminate\Support\Carbon;

/**
 * Turns "what we had" vs "what Pop Mart says now" into product events. Pure:
 * no database, so every rule is unit tested (tests/Feature/PopMartSyncTest.php).
 */
class ProductChangeDetector
{
    private const COMING_SOON = 'COMING_SOON';

    /**
     * Product-level changes. $before is null for a product we've never seen.
     *
     * @param  array{tags?: list<string>, price?: int|null, sale_start_at?: Carbon|null, sale_mode?: string}|null  $before
     * @param  array{tags: list<string>, price: int|null, sale_start_at: Carbon|null, sale_mode: string}  $after
     * @return list<array{type: ProductEventType, from: mixed, to: mixed}>
     */
    public function productChanges(?array $before, array $after): array
    {
        if ($before === null) {
            return [self::event(ProductEventType::Listed, null, ['tags' => $after['tags'], 'price' => $after['price']])];
        }

        $events = [];
        $oldTags = $before['tags'] ?? [];
        $added = array_values(array_diff($after['tags'], $oldTags));
        $removed = array_values(array_diff($oldTags, $after['tags']));

        if (in_array(self::COMING_SOON, $removed, true)) {
            $events[] = self::event(ProductEventType::SaleStarted, self::COMING_SOON, null);
        }

        $oldStart = $before['sale_start_at'] ?? null;
        $newStart = $after['sale_start_at'];
        if ($newStart !== null && ($oldStart === null || ! $oldStart->equalTo($newStart))) {
            $events[] = self::event(ProductEventType::SaleTimeChanged, $oldStart?->toIso8601String(), $newStart->toIso8601String());
        }

        $oldPrice = $before['price'] ?? null;
        if ($oldPrice !== null && $after['price'] !== null && $after['price'] !== $oldPrice) {
            $events[] = self::event($after['price'] < $oldPrice ? ProductEventType::PriceDrop : ProductEventType::PriceRise, $oldPrice, $after['price']);
        }

        $oldMode = $before['sale_mode'] ?? 'regular';
        if ($oldMode !== 'preorder' && $after['sale_mode'] === 'preorder') {
            $events[] = self::event(ProductEventType::PreorderOpened, $oldMode, 'preorder');
        } elseif ($oldMode === 'preorder' && $after['sale_mode'] !== 'preorder') {
            $events[] = self::event(ProductEventType::PreorderClosed, 'preorder', $after['sale_mode']);
        }

        foreach ($added as $tag) {
            $events[] = self::event(ProductEventType::TagAdded, null, $tag);
        }
        foreach ($removed as $tag) {
            $events[] = self::event(ProductEventType::TagRemoved, $tag, null);
        }

        return $events;
    }

    /**
     * One SKU's stock change. $before is null on the first check (no event then:
     * we don't know what it was).
     *
     * @param  array{stock: int, has_stock: bool}|null  $before
     * @param  array{stock: int, has_stock: bool}  $after
     * @return list<array{type: ProductEventType, from: mixed, to: mixed}>
     */
    public function stockChanges(?array $before, array $after, int $lowStock): array
    {
        if ($before === null) {
            return [];
        }

        if (! $before['has_stock'] && $after['has_stock']) {
            return [self::event(ProductEventType::Restocked, $before['stock'], $after['stock'])];
        }

        if ($before['has_stock'] && ! $after['has_stock']) {
            return [self::event(ProductEventType::SoldOut, $before['stock'], $after['stock'])];
        }

        if ($after['has_stock'] && $after['stock'] > 0 && $after['stock'] <= $lowStock && $before['stock'] > $lowStock) {
            return [self::event(ProductEventType::LowStock, $before['stock'], $after['stock'])];
        }

        return [];
    }

    /**
     * Whether a stock_snapshots row is due: only when stock or has_stock changed.
     *
     * @param  array{stock: int, has_stock: bool}|null  $before
     * @param  array{stock: int, has_stock: bool}  $after
     */
    public static function stockChanged(?array $before, array $after): bool
    {
        return $before === null || $before['stock'] !== $after['stock'] || $before['has_stock'] !== $after['has_stock'];
    }

    private static function event(ProductEventType $type, mixed $from, mixed $to): array
    {
        return ['type' => $type, 'from' => $from, 'to' => $to];
    }
}
