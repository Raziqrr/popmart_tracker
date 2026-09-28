<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PopNowBox extends Model
{
    protected $fillable = [
        'pop_now_set_id', 'box_no', 'position', 'status',
        'is_locked', 'locked_by_other', 'lock_started_at', 'lock_duration_seconds',
        'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'locked_by_other' => 'boolean',
            'lock_started_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(PopNowSet::class, 'pop_now_set_id');
    }

    public function hints(): HasMany
    {
        return $this->hasMany(PopNowBoxHint::class);
    }

    public function reveals(): HasMany
    {
        return $this->hasMany(PopNowBoxReveal::class);
    }

    public function lockExpiresAt(): ?\Illuminate\Support\Carbon
    {
        if (! $this->is_locked || ! $this->lock_started_at || ! $this->lock_duration_seconds) {
            return null;
        }

        return $this->lock_started_at->copy()->addSeconds($this->lock_duration_seconds);
    }
}
