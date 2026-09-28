<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Store extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'store_id', 'area', 'ns_code', 'haiding_code', 'name', 'local_name',
        'store_type', 'business_status', 'pickup_enabled', 'address', 'location',
        'latitude', 'longitude', 'opening_hours', 'timezone',
        'business_cycle_start', 'business_cycle_end',
    ];

    protected function casts(): array
    {
        return [
            'pickup_enabled' => 'boolean',
            'opening_hours' => 'array',
            'business_cycle_start' => 'datetime',
            'business_cycle_end' => 'datetime',
        ];
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
