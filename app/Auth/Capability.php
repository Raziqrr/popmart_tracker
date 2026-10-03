<?php

namespace App\Auth;

use App\Models\User;

/**
 * Every action that needs more than a guest, and what it needs. This is the
 * one place to change who can do what:
 *
 * - routes tag themselves with ->middleware('capability:<value>') (RequireCapability),
 * - GET /api/me sends allowedFor() to the frontend, which mirrors this list
 *   in resources/js/lib/capabilities.ts to show "log in" / "connect" prompts.
 *
 * Reading catalog, drops and POP NOW odds needs nothing and isn't listed.
 */
enum Capability: string
{
    case ConnectPopMart = 'connect_popmart';
    case Watchlist = 'watchlist';
    case ReportHints = 'report_hints';
    case ClaimTasks = 'claim_tasks';
    case RedeemPoints = 'redeem_points';
    case LockBoxes = 'lock_boxes';
    case AutoLock = 'auto_lock';
    case Checkout = 'checkout';
    case RefreshPopNow = 'refresh_popnow';

    public function requires(): Access
    {
        return match ($this) {
            self::ConnectPopMart, self::Watchlist, self::ReportHints => Access::Member,
            self::ClaimTasks, self::RedeemPoints, self::LockBoxes, self::AutoLock, self::Checkout, self::RefreshPopNow => Access::PopMart,
        };
    }

    /** Finishes "Log in to …" / "Connect Pop Mart to …". */
    public function label(): string
    {
        return match ($this) {
            self::ConnectPopMart => 'connect a Pop Mart account',
            self::Watchlist => 'use your watchlist',
            self::ReportHints => 'report tip-card hints',
            self::ClaimTasks => 'claim daily tasks',
            self::RedeemPoints => 'redeem lucky points',
            self::LockBoxes => 'lock boxes',
            self::AutoLock => 'set up auto-lock',
            self::Checkout => 'check out',
            self::RefreshPopNow => 'refresh live box grids',
        };
    }

    public function allows(?User $user): bool
    {
        return match ($this->requires()) {
            Access::Guest => true,
            Access::Member => $user !== null,
            Access::PopMart => $user?->hasLivePopMartSession() ?? false,
        };
    }

    /** @return list<string> the capability values this user has right now */
    public static function allowedFor(?User $user): array
    {
        return array_values(array_map(
            fn (self $capability) => $capability->value,
            array_filter(self::cases(), fn (self $capability) => $capability->allows($user)),
        ));
    }
}
