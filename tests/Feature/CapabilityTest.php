<?php

use App\Auth\Access;
use App\Auth\Capability;
use App\Auth\CapabilityException;
use App\Http\Middleware\RequireCapability;
use App\Models\PopmartAccount;
use App\Models\User;
use App\Services\Api\PopMart\PopMartError;
use App\Services\Api\PopMart\PopMartException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/*
| No database: users and accounts are unsaved models with their relations set
| by hand, and the middleware runs against a hand-built request.
*/

function fakeMember(int $id = 1, array $accounts = []): User
{
    $user = new User(['name' => 'Test User', 'email' => "user{$id}@example.com"]);
    $user->id = $id;
    $user->setRelation('popmartAccounts', new Collection($accounts));

    return $user;
}

function fakeAccount(int $ownerId, bool $live, int $id = 9): PopmartAccount
{
    $account = new PopmartAccount([
        'user_id' => $ownerId,
        'session_expires_at' => $live ? now()->addDay() : now()->subMinute(),
        'last_synced_at' => now(),
    ]);
    $account->id = $id;

    return $account;
}

/** Runs RequireCapability; returns 'passed' or the error code it threw. */
function runCapability(string $capability, ?User $user, ?PopmartAccount $routeAccount = null): string
{
    $request = Request::create('/api/test', 'POST');
    $request->setUserResolver(fn () => $user);

    $route = (new Route('POST', '/api/test', fn () => null))->bind($request);
    if ($routeAccount) {
        $route->setParameter('popmartAccount', $routeAccount);
    }
    $request->setRouteResolver(fn () => $route);

    try {
        (new RequireCapability)->handle($request, fn () => response('ok'), $capability);

        return 'passed';
    } catch (CapabilityException $e) {
        return $e->errorCode;
    } catch (PopMartException $e) {
        return $e->error->value;
    }
}

it('lets each user through only what their access level allows', function (string $capability, ?User $user, ?PopmartAccount $routeAccount, string $expected) {
    expect(runCapability($capability, $user, $routeAccount))->toBe($expected);
})->with([
    'guest, member action' => ['watchlist', null, null, 'unauthenticated'],
    'guest, Pop Mart action' => ['claim_tasks', null, null, 'unauthenticated'],
    'member, member action' => ['connect_popmart', fn () => fakeMember(), null, 'passed'],
    'member without Pop Mart' => ['lock_boxes', fn () => fakeMember(), null, 'popmart_not_connected'],
    'member with only an expired session' => ['lock_boxes', fn () => fakeMember(1, [fakeAccount(1, live: false)]), null, 'popmart_not_connected'],
    'member with a live session' => ['lock_boxes', fn () => fakeMember(1, [fakeAccount(1, live: true)]), null, 'passed'],
    'own account, live' => ['claim_tasks', fn () => fakeMember(1), fn () => fakeAccount(1, live: true), 'passed'],
    'own account, expired' => ['claim_tasks', fn () => fakeMember(1), fn () => fakeAccount(1, live: false), PopMartError::SessionExpired->value],
    'someone else\'s account' => ['claim_tasks', fn () => fakeMember(1), fn () => fakeAccount(2, live: true), 'forbidden'],
]);

it('reports what each user can do for /api/me', function () {
    $memberOnly = collect(Capability::cases())->filter(fn ($c) => $c->requires() === Access::Member)->map->value->values()->all();

    expect(Capability::allowedFor(null))->toBe([])
        ->and(Capability::allowedFor(fakeMember()))->toBe($memberOnly)
        ->and(Capability::allowedFor(fakeMember(1, [fakeAccount(1, live: true)])))->toBe(array_map(fn ($c) => $c->value, Capability::cases()));
});

it('renders capability errors in the API error shape', function () {
    $response = CapabilityException::loginRequired(Capability::ClaimTasks)->render();

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toBe([
            'error' => ['code' => 'unauthenticated', 'message' => 'Log in to claim daily tasks.', 'capability' => 'claim_tasks'],
        ]);
});

it('keeps the frontend capability list in sync with the enum', function () {
    $source = file_get_contents(resource_path('js/lib/capabilities.ts'));
    preg_match_all("/^\s+(\w+): \{ requires: '(\w+)', label: '([^']+)' \}/m", $source, $matches, PREG_SET_ORDER);
    $frontend = collect($matches)->mapWithKeys(fn ($m) => [$m[1] => [$m[2], $m[3]]])->all();

    $backend = collect(Capability::cases())->mapWithKeys(fn ($c) => [$c->value => [$c->requires()->value, $c->label()]])->all();

    expect($frontend)->toBe($backend);
});

it('protects every account route with a capability', function () {
    $public = ['api/me'];

    $unprotected = collect(Router::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/') && in_array('web', $route->gatherMiddleware(), true))
        ->reject(fn ($route) => in_array($route->uri(), $public, true))
        ->reject(fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'capability:')))
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

it('keeps account routes off the stateless api stack', function () {
    // Session-free api.php routes can't see who is logged in, so nothing there may need a capability.
    $statelessAccountRoutes = collect(Router::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('api', $route->gatherMiddleware(), true))
        ->filter(fn ($route) => str_contains($route->uri(), 'popmart-accounts'))
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($statelessAccountRoutes)->toBe([]);
});
