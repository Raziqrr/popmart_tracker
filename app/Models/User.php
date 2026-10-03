<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function popmartAccounts(): HasMany
    {
        return $this->hasMany(PopmartAccount::class);
    }

    /** The most recently synced connected account whose Pop Mart session still works. */
    public function livePopmartAccount(): ?PopmartAccount
    {
        return $this->popmartAccounts
            ->filter(fn (PopmartAccount $account) => $account->sessionIsValid())
            ->sortByDesc('last_synced_at')
            ->first();
    }

    /** "Logged in on Pop Mart": what every Access::PopMart capability needs. */
    public function hasLivePopMartSession(): bool
    {
        return $this->livePopmartAccount() !== null;
    }
}
