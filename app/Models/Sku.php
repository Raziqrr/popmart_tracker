<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sku extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'product_id', 'name', 'sku_code', 'bar_code',
        'box_type', 'price', 'currency', 'main_image',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockSnapshots(): HasMany
    {
        return $this->hasMany(StockSnapshot::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function boxHints(): HasMany
    {
        return $this->hasMany(PopNowBoxHint::class);
    }

    public function boxReveals(): HasMany
    {
        return $this->hasMany(PopNowBoxReveal::class);
    }

    public function pins(): MorphMany
    {
        return $this->morphMany(PinnedItem::class, 'pinnable');
    }

    public function watchers(): MorphMany
    {
        return $this->morphMany(WishlistItem::class, 'watchable');
    }
}
