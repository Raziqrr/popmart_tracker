<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's POP NOW auto-lock rule (docs/tickets/auto-lock.md).
 * SYNC: AutoLockRule in resources/js/types/lock.ts (served by AutoLockController::present).
 */
class AutoLockRule extends Model
{
    public const TRIGGERS = ['sale_opens', 'restock', 'chance_reached', 'narrowed'];

    /** Triggers that depend on a box's odds, so they need figure picks. */
    public const CHANCE_TRIGGERS = ['chance_reached', 'narrowed'];

    protected $fillable = [
        'user_id', 'popmart_account_id', 'product_id', 'target_kind', 'count',
        'min_confirmations', 'verified_only', 'trigger', 'min_chance',
        'lock_duration_seconds', 'checkout_immediately', 'locks_made',
        'enabled', 'expires_at', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_only' => 'boolean',
            'min_chance' => 'float',
            'checkout_immediately' => 'boolean',
            'enabled' => 'boolean',
            'expires_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PopmartAccount::class, 'popmart_account_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function picks(): HasMany
    {
        return $this->hasMany(AutoLockRulePick::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AutoLockAttempt::class);
    }

    /** Boxes the rule locks in total: count, or the sum of pick counts for specific. */
    public function totalBoxes(): int
    {
        return $this->target_kind === 'specific' ? (int) $this->picks->sum('count') : (int) $this->count;
    }

    public function boxesOwed(): int
    {
        return max(0, $this->totalBoxes() - $this->locks_made);
    }

    /** Enabled, not expired, still owed boxes, and its account's session still works. */
    public function isActive(): bool
    {
        return $this->enabled
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && $this->boxesOwed() > 0
            && $this->account?->sessionIsValid();
    }
}
