<?php

use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

/*
| The real HTTP stack (routes, middleware, Inertia, exceptions), for what a
| guest can reach without touching the database. Anything that reads a table
| needs MySQL: the migrations use generated and spatial columns, so SQLite
| can't run them.
*/

it('renders the login and register pages through Inertia', function (string $url, string $component) {
    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('auth.user', null)
            ->where('auth.capabilities', [])
            ->where('unread_alerts', 0)
            ->where('lists.pinned_product_ids', []));
})->with([
    ['/login', 'Auth/Login'],
    ['/register', 'Auth/Register'],
]);

it('tells a guest what they can do', function () {
    $this->getJson('/api/me')
        ->assertOk()
        ->assertExactJson(['user' => null, 'popmart' => null, 'capabilities' => []]);
});

it('answers guests 401 in the API error shape on every gated route', function (string $method, string $url) {
    $this->json($method, $url)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
})->with([
    ['POST', '/api/watches'],
    ['POST', '/api/pins'],
    ['GET', '/api/alerts'],
    ['GET', '/api/alert-settings'],
    ['GET', '/api/lucky-points'],
    ['POST', '/api/popmart-accounts/connect'],
    ['POST', '/api/popmart-accounts/1/claim-tasks'],
    ['POST', '/api/popmart-accounts/1/redeem-points'],
    ['POST', '/api/pop-now-sets/1/refresh'],
    ['GET', '/api/auto-lock'],
    ['DELETE', '/api/auto-lock/1'],
]);

it('does not reveal which account ids exist', function () {
    // Route-model binding runs after the capability check, so a missing id is
    // not a 404 for a guest: 1 and 999999 answer the same.
    $first = $this->postJson('/api/popmart-accounts/1/claim-tasks');
    $other = $this->postJson('/api/popmart-accounts/999999/claim-tasks');

    expect($first->status())->toBe(401)
        ->and($other->status())->toBe($first->status())
        ->and($other->json())->toBe($first->json());
});

it('keeps the public odds endpoints stateless', function () {
    $route = app('router')->getRoutes()->match(Request::create('/api/pop-now-sets/1/predictions'));

    expect($route->gatherMiddleware())->toContain('api')->not->toContain('web');
});

it('sends the guest to log in for pages that need an account', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with(['/watchlist', '/alerts', '/auto-lock']);

it('allows guests to view the POP NOW page with expected props', function () {
    $this->get('/pop-now')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PopNow')
            ->has('products')
            ->has('selected_product_id')
            ->has('sets'));
});
