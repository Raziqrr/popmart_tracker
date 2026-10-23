<?php

namespace App\Models;

use App\Services\Api\PopMart\PopMartError;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Locator\Country;
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

    /** The X-Area this account's calls go out with. Unknown areas fall back to Malaysia. */
    public function country(): Country
    {
        return Country::tryFrom((string) $this->area) ?? Country::Malaysia;
    }

    public function sessionIsValid(): bool
    {
        return $this->session_expires_at === null || $this->session_expires_at->isFuture();
    }

    /** Pop Mart stopped accepting this session; the UI shows "reconnect" from now on. */
    public function markSessionExpired(): void
    {
        if ($this->sessionIsValid()) {
            $this->update(['session_expires_at' => now()]);
        }
    }

    /**
     * Run Pop Mart calls with this account's session. The callback gets the
     * cookie and country to pass to each endpoint. If Pop Mart says the session
     * is gone, the account is marked expired before the exception carries on.
     *
     * @template T
     *
     * @param  callable(string, Country): T  $callback
     * @return T
     *
     * @throws PopMartException
     */
    public function usingSession(callable $callback): mixed
    {
        // Already known dead: don't spend a Pop Mart call finding out again.
        if (! $this->sessionIsValid()) {
            throw PopMartException::sessionExpired();
        }

        try {
            return $callback($this->session_cookie, $this->country());
        } catch (PopMartException $e) {
            if ($e->is(PopMartError::SessionExpired)) {
                $this->markSessionExpired();
            }

            throw $e;
        }
    }
}
