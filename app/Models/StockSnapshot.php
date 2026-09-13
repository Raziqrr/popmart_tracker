<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['sku_id', 'stock', 'has_stock', 'checked_at'];

    protected function casts(): array
    {
        return [
            'has_stock' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
