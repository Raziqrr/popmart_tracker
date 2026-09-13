<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'tags', 'price', 'sales', 'checked_at'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
