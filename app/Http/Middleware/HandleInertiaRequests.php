<?php

namespace App\Http\Middleware;

use App\Auth\AuthState;
use App\Http\Controllers\ListController;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Props every Inertia page gets (SharedProps in resources/js/types/page.ts):
 * who's logged in and what they can do, their pin/watch lists, and the
 * unread alert count for the header.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => fn () => AuthState::for($user),
            'lists' => fn () => ListController::listsFor($user),
            'unread_alerts' => fn () => $user?->unreadNotifications()->count() ?? 0,
            'area' => config('apis.popmart.areas')[0] ?? 'MY',
        ];
    }
}
