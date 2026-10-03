<?php

namespace App\Services\PopMart\Sync;

use App\Models\Collection;
use App\Models\Product;
use App\Models\Sku;
use Illuminate\Support\Facades\DB;

/** Products anyone pins or watches, directly or through one of its SKUs or its collection. Those poll hot. */
class WatchedProducts
{
    /** @return array<string, true> product ids */
    public function ids(): array
    {
        $rows = DB::table('pinned_items')->select('pinnable_type as type', 'pinnable_id as id')
            ->union(DB::table('wishlist_items')->select('watchable_type as type', 'watchable_id as id'))
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->pluck('id')->all());

        $ids = array_merge(
            $rows[(new Product)->getMorphClass()] ?? [],
            Sku::whereIn('id', $rows[(new Sku)->getMorphClass()] ?? [])->pluck('product_id')->all(),
            Product::whereIn('collection_id', $rows[(new Collection)->getMorphClass()] ?? [])->pluck('id')->all(),
        );

        return array_fill_keys($ids, true);
    }
}
