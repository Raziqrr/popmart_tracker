<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One box an auto-lock rule (or a manual lock) tried to hold: also the audit
 * log of what was done with the user's session. SYNC: LockAttempt in
 * resources/js/types/lock.ts.
 */
class AutoLockAttempt extends Model
{
    public const PENDING = 'pending';

    public const LOCKED = 'locked';

    public const EXPIRED = 'expired';

    public const FAILED = 'failed';

    protected $fillable = [
        'auto_lock_rule_id', 'user_id', 'pop_now_box_id', 'sku_id', 'chance_at_lock',
        'status', 'attempted_at', 'locked_at', 'lock_expires_at', 'error',
        'raw_response', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'chance_at_lock' => 'float',
            'attempted_at' => 'datetime',
            'locked_at' => 'datetime',
            'lock_expires_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutoLockRule::class, 'auto_lock_rule_id');
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(PopNowBox::class, 'pop_now_box_id');
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
