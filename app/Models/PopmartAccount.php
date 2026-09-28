<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PopmartAccount extends Model
{
    protected $fillable = [
        'user_id', 'popmart_member_id', 'area', 'session_cookie',
        'session_expires_at', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'session_cookie' => 'encrypted',
            'session_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
