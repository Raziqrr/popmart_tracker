<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A connected account's lucky points, synced by LuckyPointsSync.
 * SYNC: served as LuckyPoints (resources/js/types/account.ts) by LuckyPointsController.
 */
class PopmartAccountPoints extends Model
{
    protected $fillable = [
        'popmart_account_id', 'balance', 'redeem_minimum',
        'expiring_points', 'expiring_at', 'raw_payload', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'expiring_at' => 'datetime',
            'synced_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PopmartAccount::class, 'popmart_account_id');
    }
}
