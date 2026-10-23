<?php

namespace App\Services\PopMart\Sync\Adapters;

use App\Services\Api\PopMart\Endpoints\Ec\CheckSkuStock;

/**
 * Stock per SKU from CheckSkuStock.
 *
 * UNCONFIRMED: the request (by spuId) and the item field names are guesses.
 * The endpoint itself and its "json" wrapper were confirmed with a real call
 * (see PopMartEndpoint).
 */
class SkuStockAdapter extends Adapter
{
    public const CONFIRMED = true;

    public static function endpoint(): string
    {
        return CheckSkuStock::class;
    }

    public static function request(string $id, int $quantity = 1): array
    {
        return ['skuId' => $id, 'quantity' => $quantity, 'spuId' => $id];
    }

    /**
     * @return array<string, array{stock: int, has_stock: bool}> keyed by sku id
     */
    public static function map(mixed $json, ?string $skuId = null): array
    {
        $stock = [];

        // Direct single-SKU response from CheckSkuStock: {"availableQuantity": 2, "hasStock": true}
        if (is_array($json) && (isset($json['availableQuantity']) || isset($json['hasStock']))) {
            $count = self::pick($json, ['availableQuantity', 'stock', 'remainStock', 'availableStock', 'quantity']);
            $hasStock = self::pick($json, ['hasStock', 'inStock', 'isInStock']);
            $id = $skuId ?? (string) self::pick($json, ['skuId', 'id'], 'default');

            return [
                $id => [
                    'stock' => max(0, (int) ($count ?? 0)),
                    'has_stock' => $hasStock !== null ? (bool) $hasStock : (int) $count > 0,
                ],
            ];
        }

        foreach (self::items($json, ['list', 'skus', 'skuStocks', 'data', 'items']) as $item) {
            $id = (string) self::need($item, ['skuId', 'id'], 'sku id');
            $count = self::pick($item, ['availableQuantity', 'stock', 'remainStock', 'availableStock', 'quantity']);
            $hasStock = self::pick($item, ['hasStock', 'inStock', 'isInStock']);

            if ($count === null && $hasStock === null) {
                self::need($item, ['stock', 'hasStock'], 'stock');
            }

            // Masked products only say whether there's stock, not how much.
            $stock[$id] = [
                'stock' => max(0, (int) ($count ?? 0)),
                'has_stock' => $hasStock !== null ? (bool) $hasStock : (int) $count > 0,
            ];
        }

        return $stock;
    }
}
