<?php

namespace App\Services\Alerts;

use App\Services\PopMart\Sync\ProductEventType;

/**
 * Who should hear about one product event, from wishlist rows. Pure, so the
 * rules are unit tested:
 *
 * - watching the product: every event about it
 * - watching one figure (SKU): events about that figure, and product-wide
 *   events (sale opening, pre-order, price) of its product
 * - watching a collection: only news a collection follower cares about
 *   (new listing, sale opening or time, pre-order), not every stock wobble
 */
final class AlertTargets
{
    public const COLLECTION_TYPES = [
        ProductEventType::Listed,
        ProductEventType::SaleStarted,
        ProductEventType::SaleTimeChanged,
        ProductEventType::PreorderOpened,
    ];

    /**
     * @param  array{type: ProductEventType, product_id: string, sku_id: string|null, collection_id: string|null}  $event
     * @param  list<array{user_id: int, kind: 'product'|'sku'|'collection', id: string}>  $watches
     * @param  array<string, string>  $skuProduct  sku id => product id
     * @return list<int> user ids, unique
     */
    public static function users(array $event, array $watches, array $skuProduct): array
    {
        $users = [];

        foreach ($watches as $watch) {
            $match = match ($watch['kind']) {
                'product' => $watch['id'] === $event['product_id'],
                'sku' => $event['sku_id'] !== null
                    ? $watch['id'] === $event['sku_id']
                    : ($skuProduct[$watch['id']] ?? null) === $event['product_id'],
                'collection' => $event['collection_id'] !== null
                    && $watch['id'] === $event['collection_id']
                    && in_array($event['type'], self::COLLECTION_TYPES, true),
            };

            if ($match) {
                $users[$watch['user_id']] = true;
            }
        }

        return array_keys($users);
    }
}
