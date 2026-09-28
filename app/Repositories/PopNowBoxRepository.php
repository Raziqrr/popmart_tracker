<?php

namespace App\Repositories;

use App\Models\PopNowBox;
use App\Models\PopNowSet;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PopNowBoxRepository
{
    // Every non-secret SKU for this box's product — the full candidate pool
    // before any exclusions are applied. Secret/chase SKUs are excluded: they
    // don't fit the one-SKU-per-box permutation a normal set is drawn from,
    // and nothing in the API surfaces whether a given box is a secret slot.
    public function allPossibleSkus(PopNowBox $box): EloquentCollection
    {
        return $this->nonSecretSkusForProduct($box->set->product_id);
    }

    public function nonSecretSkusForProduct(string $productId): EloquentCollection
    {
        return Sku::query()
            ->where('product_id', $productId)
            ->where(fn ($q) => $q->where('box_type', '!=', 'secret')->orWhereNull('box_type'))
            ->get();
    }

    // All boxes in a set, with hints/reveals eager loaded — needed for
    // cross-box (set-wide) prediction, not just a single box in isolation.
    public function boxesForSet(PopNowSet $set): EloquentCollection
    {
        return $set->boxes()->with('hints', 'reveals')->get();
    }

    // Every set recorded for a product — the unit a product-wide ranking
    // needs to predict set-by-set (predictions are only valid within one
    // set's own closed permutation, never mixed across sets).
    public function setsForProduct(string $productId): EloquentCollection
    {
        return PopNowSet::where('product_id', $productId)->get();
    }

    // SKU IDs this box has been confirmed (via real tip-card hints) to NOT be.
    public function excludedSkuIds(PopNowBox $box): Collection
    {
        return $box->hints()->pluck('sku_id');
    }

    // The confirmed ground-truth SKU, if this box has actually been revealed
    // (via a real purchase/unbox) — null if still unrevealed.
    public function confirmedSku(PopNowBox $box): ?Sku
    {
        $skuId = $box->reveals()->value('sku_id');

        return $skuId ? Sku::find($skuId) : null;
    }
}
