<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One action taken on a user's real Pop Mart account (App\Services\PopMart\AccountActions). */
class PopmartAction extends Model
{
    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    protected $fillable = [
        'user_id', 'popmart_account_id', 'action', 'idempotency_key',
        'request', 'status', 'error', 'response',
    ];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'response' => 'array',
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
}
