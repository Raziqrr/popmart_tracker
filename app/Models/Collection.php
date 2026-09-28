<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'theme_id', 'area', 'name', 'slug'];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
