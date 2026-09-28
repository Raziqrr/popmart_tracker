<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * SYNC: mirrored by hand in resources/js/types/catalog.ts (Product, ProductCardData)
 * and ui-lab fixtures. Column/cast/enum changes must be copied there; see app/Models/README.md.
 */
class Product extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'theme_id', 'collection_id', 'category_id', 'name', 'slug',
        'price', 'currency', 'business_type', 'spec_type', 'area_codes',
        'sale_start_at', 'sale_end_at', 'tags', 'sales', 'remain_stock',
        'warehouse', 'raw_payload', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'area_codes' => 'array',
            'tags' => 'array',
            'raw_payload' => 'array',
            'sale_start_at' => 'datetime',
            'sale_end_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_new' => 'boolean',
            'is_sold_out' => 'boolean',
            'is_coming_soon' => 'boolean',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ProductSnapshot::class);
    }

    public function popNowSets(): HasMany
    {
        return $this->hasMany(PopNowSet::class);
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
