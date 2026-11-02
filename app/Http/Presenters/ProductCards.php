<?php

namespace App\Http\Presenters;

use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\ProductSnapshot;
use App\Models\StockSnapshot;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds ProductCardData (resources/js/types/catalog.ts) for a set of
 * products in a fixed number of queries, whatever the count: the derived
 * fields (image, theme name, previous price, stock history, last change)
 * come from batched lookups, not one query per product.
 */
class ProductCards
{
    /** Hours of stock history behind each card's sparkline. */
    private const HISTORY_HOURS = 24;

    /** Most points per sparkline. */
    private const HISTORY_POINTS = 24;

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    public function present(Collection $products): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $products->loadMissing(['theme', 'skus']);
        $ids = $products->modelKeys();

        $lastChange = ProductEvent::whereIn('id', ProductEvent::whereIn('product_id', $ids)
            ->whereNotIn('type', ['tag_added', 'tag_removed'])
            ->groupBy('product_id')
            ->selectRaw('MAX(id)'))
            ->get()
            ->keyBy('product_id');

        $snapshots = ProductSnapshot::whereIn('product_id', $ids)->orderByDesc('checked_at')->get(['product_id', 'price', 'checked_at'])->groupBy('product_id');
        $history = $this->stockHistory($products);

        return $products->map(function (Product $product) use ($lastChange, $snapshots, $history) {
            $event = $lastChange->get($product->id);
            $previousPrice = $snapshots->get($product->id)?->first(fn (ProductSnapshot $s) => $s->price !== $product->price)?->price;
            $firstSku = $product->skus->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'currency' => $product->currency,
                'business_type' => $product->business_type,
                'spec_type' => $product->spec_type,
                'sale_start_at' => $product->sale_start_at?->toIso8601String(),
                'remain_stock' => $product->remain_stock,
                'warehouse' => $product->warehouse,
                'is_new' => (bool) $product->is_new,
                'is_sold_out' => (bool) $product->is_sold_out,
                'is_coming_soon' => (bool) $product->is_coming_soon,
                'last_seen_at' => $product->last_seen_at?->toIso8601String(),
                'image_url' => $firstSku?->main_image ?? ($product->raw_payload['mainImage'] ?? null),
                'theme_name' => $product->theme?->name,
                'previous_price' => $previousPrice,
                'stock_history' => $history[$product->id] ?? [],
                'last_changed_at' => $event?->occurred_at?->toIso8601String(),
                'last_change' => $event ? EventFeed::change($event) : null,
                'sell_rate_per_hour' => $product->sell_rate_per_hour,
                'hours_to_sell_out' => $product->hours_to_sell_out,
                'first_seen_at' => $product->created_at?->toIso8601String(),
                'category_name' => null,
                'skus' => $product->skus->map(fn ($sku) => [
                    'id' => $sku->id,
                    'name' => $sku->name,
                    'sku_code' => $sku->sku_code,
                    'bar_code' => $sku->bar_code,
                    'is_secret' => $sku->box_type === 'secret',
                    'image_url' => $sku->main_image,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Total stock per product over the last HISTORY_HOURS, as points
     * { checked_at, stock }. Snapshots are only written on change, so each
     * SKU's stock at any moment is its latest row before it.
     *
     * @return array<string, list<array{checked_at: string, stock: int}>>
     */
    private function stockHistory(Collection $products): array
    {
        $skuProduct = $products->flatMap(fn (Product $p) => $p->skus->mapWithKeys(fn ($sku) => [$sku->id => $p->id]))->all();
        if ($skuProduct === []) {
            return [];
        }

        $since = now()->subHours(self::HISTORY_HOURS);
        $rows = StockSnapshot::whereIn('sku_id', array_keys($skuProduct))
            ->where('checked_at', '>=', $since)
            ->orderBy('checked_at')
            ->get(['sku_id', 'stock', 'checked_at']);

        // Each SKU's stock as of the start of the window.
        $current = [];
        foreach (StockSnapshot::whereIn('id', StockSnapshot::whereIn('sku_id', array_keys($skuProduct))
            ->where('checked_at', '<', $since)
            ->groupBy('sku_id')
            ->selectRaw('MAX(id)'))->get(['sku_id', 'stock']) as $row) {
            $current[$row->sku_id] = $row->stock;
        }

        $skusOf = [];
        foreach ($skuProduct as $skuId => $productId) {
            $skusOf[$productId][] = $skuId;
        }

        $series = [];
        foreach ($rows as $row) {
            $current[$row->sku_id] = $row->stock;
            $productId = $skuProduct[$row->sku_id];
            $total = array_sum(array_map(fn ($skuId) => $current[$skuId] ?? 0, $skusOf[$productId]));
            $series[$productId][] = ['checked_at' => $row->checked_at->toIso8601String(), 'stock' => $total];
        }

        return array_map(fn (array $points) => array_slice($points, -self::HISTORY_POINTS), $series);
    }
}
