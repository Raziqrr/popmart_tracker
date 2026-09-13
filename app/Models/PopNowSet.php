<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PopNowSet extends Model
{
    protected $fillable = [
        'product_id', 'set_no', 'set_width', 'set_height',
        'total_boxes', 'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(PopNowBox::class);
    }
}
