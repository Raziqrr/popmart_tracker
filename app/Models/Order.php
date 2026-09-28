<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'popmart_account_id', 'order_dispatch_num', 'status',
        'area_code', 'currency', 'raw_payload', 'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'placed_at' => 'datetime',
        ];
    }

    public function popmartAccount(): BelongsTo
    {
        return $this->belongsTo(PopmartAccount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function boxReveals(): HasMany
    {
        return $this->hasMany(PopNowBoxReveal::class);
    }
}
