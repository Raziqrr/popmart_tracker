<?php

namespace App\Services\PopMart\Sync\Adapters;

use App\Services\Api\PopMart\Endpoints\Ec\FindByIds;
use App\Services\Api\PopMart\Endpoints\Ec\FindDrawSpusByCollectionId;
use App\Services\Api\PopMart\Endpoints\Ec\FindSummariesByCollectionId;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\UnexpectedPayload;

/**
 * Products (SPUs) from Pop Mart's listing and detail endpoints:
 * FindSummariesByCollectionId, FindDrawSpusByCollectionId (POP NOW) and FindByIds.
 *
 * UNCONFIRMED: request payloads and field names are educated guesses based on
 * the columns in create_products_table / create_skus_table (which mirror Pop
 * Mart's naming). Where several names are plausible, all are tried in order.
 *
 * @phpstan-type ProductData array{
 *     id: string, name: string, price: int|null, currency: string, business_type: 'shop'|'draw'|null,
 *     spec_type: string|null, area_codes: list<string>, sale_start_at: \Illuminate\Support\Carbon|null,
 *     sale_end_at: \Illuminate\Support\Carbon|null, tags: list<string>, sales: int|null,
 *     remain_stock: int|null, category_id: string|null, sale_mode: 'regular'|'preorder',
 *     collection: array{collection_id: string, collection_name: string|null, theme_id: string|null, theme_name: string|null}|null,
 *     raw_payload: array, skus: list<SkuData>|null
 * }
 * @phpstan-type SkuData array{
 *     id: string, name: string, sku_code: string|null, bar_code: string|null, box_type: string|null,
 *     price: int|null, currency: string, main_image: string|null
 * }
 */
class SpuAdapter extends Adapter
{
    public const CONFIRMED = false;

    private const CURRENCY = ['MY' => 'MYR', 'SG' => 'SGD', 'TH' => 'THB'];

    public static function collectionRequest(string $collectionId): array
    {
        return ['collectionId' => $collectionId];
    }

    public static function idsRequest(array $spuIds): array
    {
        return ['ids' => array_values($spuIds)];
    }

    /** @return array<class-string, 'shop'|'draw'> listing endpoints per collection, and the business_type each implies */
    public static function listingEndpoints(): array
    {
        return [FindSummariesByCollectionId::class => 'shop', FindDrawSpusByCollectionId::class => 'draw'];
    }

    public static function detailsEndpoint(): string
    {
        return FindByIds::class;
    }

    /**
     * One item we can't read is skipped; if none of them can be read, the field
     * names are wrong (not the data), so that throws instead of looking like an
     * empty listing.
     *
     * @return list<ProductData>
     *
     * @throws UnexpectedPayload
     */
    public static function mapList(mixed $json, Country $area, ?string $businessType = null): array
    {
        $items = self::items($json, ['list', 'items', 'records', 'data', 'spus', 'data.list']);
        $products = [];
        $firstError = null;

        foreach ($items as $item) {
            try {
                $products[] = self::mapProduct(is_array($item) ? $item : [], $area, $businessType);
            } catch (UnexpectedPayload $e) {
                $firstError ??= $e;
            }
        }

        if ($products === [] && $firstError !== null) {
            throw $firstError;
        }

        return $products;
    }

    /** @return ProductData */
    public static function mapProduct(array $item, Country $area, ?string $businessType = null): array
    {
        $currency = self::pick($item, ['currency', 'currencyCode'], self::CURRENCY[$area->value] ?? 'MYR');
        $skus = self::pick($item, ['skus', 'skuList']);
        $type = self::pick($item, ['businessType', 'bizType']);

        return [
            'id' => (string) self::need($item, ['spuId', 'id'], 'spu id'),
            'name' => (string) self::need($item, ['title', 'name', 'spuName'], 'name'),
            'price' => self::toMinorUnits(self::pick($item, ['price', 'minPrice', 'salePrice'])),
            'currency' => (string) $currency,
            // Null when neither the endpoint nor the item says: the writer then keeps what it had.
            'business_type' => $businessType ?? match (true) {
                $type === null => null,
                in_array(strtolower((string) $type), ['draw', 'pop_now', 'popnow'], true) => 'draw',
                default => 'shop',
            },
            'spec_type' => self::pick($item, ['specType']),
            'area_codes' => self::stringList(self::pick($item, ['areaCodes'], [$area->value])),
            'sale_start_at' => self::toTime(self::pick($item, ['saleStartTime', 'saleStartAt', 'startTime', 'onSaleTime'])),
            'sale_end_at' => self::toTime(self::pick($item, ['saleEndTime', 'saleEndAt', 'endTime'])),
            'tags' => self::stringList(self::pick($item, ['tags', 'tagList'], [])),
            'sales' => self::pick($item, ['sales', 'soldCount']),
            'remain_stock' => self::pick($item, ['remainStock', 'stock']),
            'category_id' => self::pick($item, ['categoryId']),
            'sale_mode' => self::saleMode($item),
            'collection' => self::collectionRef($item),
            'raw_payload' => $item,
            'skus' => is_array($skus) ? array_map(fn ($sku) => self::mapSku($sku, (string) $currency), $skus) : null,
        ];
    }

    /** @return SkuData */
    public static function mapSku(array $sku, string $currency): array
    {
        return [
            'id' => (string) self::need($sku, ['skuId', 'id'], 'sku id'),
            'name' => (string) self::need($sku, ['title', 'name', 'skuName'], 'sku name'),
            'sku_code' => self::pick($sku, ['skuCode']),
            'bar_code' => self::pick($sku, ['barCode']),
            'box_type' => self::pick($sku, ['boxType']),
            'price' => self::toMinorUnits(self::pick($sku, ['price', 'salePrice'])),
            'currency' => $currency,
            'main_image' => self::pick($sku, ['mainImage', 'image', 'mainImageUrl']),
        ];
    }

    /**
     * Which collection (and theme / IP) a product says it belongs to, for
     * collection discovery. Null when the item doesn't say.
     *
     * @return array{collection_id: string, collection_name: string|null, theme_id: string|null, theme_name: string|null}|null
     */
    public static function collectionRef(array $item): ?array
    {
        $collectionId = self::pick($item, ['collectionId', 'collection.id']);

        if (! is_string($collectionId) || $collectionId === '') {
            return null;
        }

        $themeId = self::pick($item, ['ipId', 'themeId', 'ip.id']);

        return [
            'collection_id' => strtolower($collectionId),
            'collection_name' => self::pick($item, ['collectionName', 'collection.name', 'collectionTitle']),
            'theme_id' => is_string($themeId) && $themeId !== '' ? strtolower($themeId) : null,
            'theme_name' => self::pick($item, ['ipName', 'themeName', 'ip.name']),
        ];
    }

    /**
     * Pre-order detection. NOT KNOWN YET: no field we've seen marks a
     * pre-order. Pop Mart's raw payloads are kept (products.raw_payload) and
     * new fields are recorded (popmart_payload_fields), so the first real
     * pre-order shows which field to read here. Until then: always regular.
     */
    public static function saleMode(array $item): string
    {
        return 'regular';
    }
}
