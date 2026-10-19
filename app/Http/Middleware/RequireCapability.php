<?php

namespace App\Http\Middleware;

use App\Auth\Access;
use App\Auth\Capability;
use App\Auth\CapabilityException;
use App\Models\PopmartAccount;
use App\Services\Api\PopMart\PopMartException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: ->middleware('capability:claim_tasks').
 *
 * - Member capabilities need a logged-in user (401 unauthenticated).
 * - PopMart capabilities also need a live Pop Mart session. When the route has
 *   a {popmartAccount}, it must be the user's own (403 forbidden) and its
 *   session must still work (409 popmart_session_expired). Without one, any
 *   of the user's connected accounts with a live session will do
 *   (409 popmart_not_connected).
 */
class RequireCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $capability = Capability::from($capability);
        $user = $request->user();

        if ($capability->requires() === Access::Guest) {
            return $next($request);
        }

        if ($user === null) {
            throw CapabilityException::loginRequired($capability);
        }

        if ($capability->requires() === Access::PopMart) {
            // This runs before route-model binding (see bootstrap/app.php), so the
            // parameter may still be a raw id. Another user's account and one that
            // doesn't exist answer the same 403: ids can't be probed.
            $param = $request->route('popmartAccount');
            $account = $param instanceof PopmartAccount ? $param : (is_scalar($param) ? PopmartAccount::find($param) : null);

            if ($param !== null) {
                if ($account === null || Gate::forUser($user)->denies('use', $account)) {
                    throw CapabilityException::notYourAccount();
                }

                if (! $account->sessionIsValid()) {
                    throw PopMartException::sessionExpired();
                }
            } elseif (! $user->hasLivePopMartSession()) {
                throw CapabilityException::popMartRequired($capability);
            }
        }

        return $next($request);
    }
}
