<?php

namespace App\Repositories;

use App\Models\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ProductRepository
{
    public function withLatestStockByCollection(): EloquentCollection
    {
        return Collection::with('products.skus.latestStockSnapshot')->get();
    }
}
