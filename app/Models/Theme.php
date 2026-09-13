<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'slug', 'main_image'];

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
