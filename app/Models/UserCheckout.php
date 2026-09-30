<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Held POP NOW boxes sent to checkout, waiting for the user to pay.
 *
 * pending → ready (checkoutValidate passed; the pay window runs until expires_at)
 *         → paid   (the order sync saw the order)
 *         → failed (not paid in time, or Pop Mart refused the checkout)
 */
class UserCheckout extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    public const FAILED_EXPIRED = 'expired';
    public const FAILED_INVALID = 'invalid';

    protected $fillable = [
        'user_id', 'popmart_account_id', 'pop_now_set_id', 'auto_lock_rule_id',
        'status', 'failure_reason', 'hold_seconds',
        'ready_at', 'expires_at', 'paid_at', 'failed_at', 'order_id', 'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'hold_seconds' => 'integer',
            'ready_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function popmartAccount(): BelongsTo
    {
        return $this->belongsTo(PopmartAccount::class);
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(PopNowSet::class, 'pop_now_set_id');
    }

    public function boxes(): BelongsToMany
    {
        return $this->belongsToMany(PopNowBox::class, 'user_checkout_boxes');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Ready checkouts whose pay window has closed. */
    public function scopeOverdue(Builder $query, ?Carbon $now = null): Builder
    {
        return $query->where('status', self::STATUS_READY)->where('expires_at', '<=', $now ?? now());
    }

    /**
     * Pop Mart accepted the checkout. $lockRemainingSeconds comes from its
     * checkoutValidate / getMinLockTTL response: the soonest box hold, which
     * is the whole checkout's deadline.
     */
    public function markReady(int $lockRemainingSeconds, ?array $rawResponse = null): void
    {
        $now = now();

        $this->update([
            'status' => self::STATUS_READY,
            'hold_seconds' => $lockRemainingSeconds,
            'ready_at' => $now,
            'expires_at' => $now->copy()->addSeconds($lockRemainingSeconds),
            'raw_response' => $rawResponse,
        ]);
    }

    public function markPaid(Order $order): void
    {
        $this->update(['status' => self::STATUS_PAID, 'paid_at' => now(), 'order_id' => $order->id]);
    }

    public function markFailed(string $reason, ?array $rawResponse = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'failed_at' => now(),
            'raw_response' => $rawResponse ?? $this->raw_response,
        ]);
    }

    public function secondsLeft(): ?int
    {
        if ($this->status !== self::STATUS_READY || ! $this->expires_at) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
