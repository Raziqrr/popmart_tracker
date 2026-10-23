<?php

namespace App\Services\PopMart\Sync;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\ProductSnapshot;
use App\Models\Sku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves one product from an adapter (SpuAdapter::mapProduct) and records what
 * changed: product_events, plus a product_snapshots row only when something did.
 */
class ProductWriter
{
    public function __construct(private ProductChangeDetector $detector) {}

    /**
     * @param  array  $data  SpuAdapter ProductData
     * @return list<ProductEventType>
     */
    public function write(array $data, ?Collection $collection = null): array
    {
        return DB::transaction(function () use ($data, $collection) {
            $product = Product::find($data['id']);
            $now = now();

            $before = $product ? [
                'tags' => $product->tags ?? [],
                'price' => $product->price,
                'sale_start_at' => $product->sale_start_at,
                'sale_mode' => $product->sale_mode ?? 'regular',
            ] : null;

            $after = [
                'tags' => $data['tags'],
                'price' => $data['price'] ?? $product?->price,
                'sale_start_at' => $data['sale_start_at'] ?? $product?->sale_start_at,
                'sale_mode' => $data['sale_mode'],
            ];

            $events = $this->detector->productChanges($before, $after);

            // Only overwrite what this response actually told us.
            $attributes = array_filter([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'price' => $data['price'],
                'currency' => $data['currency'],
                'business_type' => $data['business_type'],
                'spec_type' => $data['spec_type'],
                'area_codes' => $data['area_codes'],
                'sale_start_at' => $data['sale_start_at'],
                'sale_end_at' => $data['sale_end_at'],
                'tags' => $data['tags'],
                'sales' => $data['sales'],
                'remain_stock' => $data['remain_stock'],
                'category_id' => $data['category_id'],
                'sale_mode' => $data['sale_mode'],
                'raw_payload' => $data['raw_payload'],
                'collection_id' => $collection?->id,
                'theme_id' => $collection?->theme_id,
                'last_seen_at' => $now,
            ], fn ($value) => $value !== null);

            if ($product === null) {
                // Columns a brand-new row can't leave empty.
                $attributes += ['price' => 0, 'business_type' => 'shop', 'next_stock_check_at' => $now];
            }

            $product = Product::updateOrCreate(['id' => $data['id']], $attributes);

            foreach ($data['skus'] ?? [] as $sku) {
                Sku::updateOrCreate(['id' => $sku['id']], array_filter(
                    ['product_id' => $product->id, ...$sku, 'price' => $sku['price'] ?? $product->price],
                    fn ($value) => $value !== null,
                ));
            }

            if ($events !== []) {
                ProductSnapshot::create([
                    'product_id' => $product->id,
                    'tags' => $product->tags,
                    'price' => $product->price,
                    'sales' => $product->sales,
                    'checked_at' => $now,
                ]);

                ProductEvent::insert(array_map(fn (array $event) => [
                    'product_id' => $product->id,
                    'sku_id' => null,
                    'type' => $event['type']->value,
                    'from' => json_encode($event['from']),
                    'to' => json_encode($event['to']),
                    'occurred_at' => $now,
                ], $events));
            }

            return array_column($events, 'type');
        });
    }
}
