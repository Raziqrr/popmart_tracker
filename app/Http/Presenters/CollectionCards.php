<?php

namespace App\Http\Presenters;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductEvent;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

/** CollectionSummary (resources/js/types/catalog.ts): a collection with its products' status counts. */
class CollectionCards
{
    /** Mirrors LOW_STOCK_THRESHOLD in resources/js/lib/productStatus.ts. */
    private const LOW_STOCK = 20;

    /**
     * @param  EloquentCollection<int, Collection>  $collections
     * @return list<array<string, mixed>>
     */
    public function present(EloquentCollection $collections): array
    {
        if ($collections->isEmpty()) {
            return [];
        }

        $collections->loadMissing(['theme', 'products.skus']);
        $productIds = $collections->flatMap(fn (Collection $c) => $c->products->modelKeys())->all();
        $lastEventAt = ProductEvent::whereIn('product_id', $productIds)
            ->selectRaw('product_id, max(occurred_at) as at')
            ->groupBy('product_id')
            ->pluck('at', 'product_id');

        return $collections->map(function (Collection $collection) use ($lastEventAt) {
            $products = $collection->products;
            $nextSale = $products->pluck('sale_start_at')->filter(fn ($at) => $at?->isFuture())->min();
            $lastChanged = $products->map(fn (Product $p) => $lastEventAt[$p->id] ?? null)->filter()->max();

            return [
                'id' => $collection->id,
                'theme_id' => $collection->theme_id,
                'area' => $collection->area,
                'name' => $collection->name,
                'slug' => $collection->slug,
                'name_confirmed' => (bool) $collection->name_confirmed,
                'theme_name' => $collection->theme?->name,
                'image_url' => $products->map(fn (Product $p) => $p->skus->first()?->main_image)->filter()->first(),
                'product_count' => $products->count(),
                'status_counts' => self::statusCounts($products),
                'next_sale_at' => $nextSale?->toIso8601String(),
                'first_seen_at' => $collection->created_at?->toIso8601String(),
                'last_changed_at' => $lastChanged ? Carbon::parse($lastChanged)->toIso8601String() : null,
            ];
        })->values()->all();
    }

    /** @return array{in_stock: int, low_stock: int, coming_soon: int, sold_out: int} same rules as productStatus() */
    public static function statusCounts($products): array
    {
        $counts = ['in_stock' => 0, 'low_stock' => 0, 'coming_soon' => 0, 'sold_out' => 0];

        foreach ($products as $product) {
            $status = match (true) {
                (bool) $product->is_coming_soon => 'coming_soon',
                (bool) $product->is_sold_out => 'sold_out',
                $product->remain_stock !== null && $product->remain_stock <= self::LOW_STOCK => 'low_stock',
                default => 'in_stock',
            };
            $counts[$status]++;
        }

        return $counts;
    }
}
