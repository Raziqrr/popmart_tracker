<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopNowBoxReveal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pop_now_box_id', 'sku_id', 'revealed_by_user_id',
        'order_id', 'source', 'revealed_at',
    ];

    protected function casts(): array
    {
        return [
            'revealed_at' => 'datetime',
        ];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(PopNowBox::class, 'pop_now_box_id');
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function revealedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revealed_by_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
