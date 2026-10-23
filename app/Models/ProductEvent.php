<?php

namespace App\Models;

use App\Services\PopMart\Sync\ProductEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something that changed about a product, written by the Pop Mart sync
 * (App\Services\PopMart\Sync). Alerts and auto-lock triggers read these.
 * SYNC: ProductEventType is mirrored in resources/js/types/catalog.ts.
 */
class ProductEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'sku_id', 'type', 'from', 'to', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'type' => ProductEventType::class,
            'from' => 'json',
            'to' => 'json',
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
