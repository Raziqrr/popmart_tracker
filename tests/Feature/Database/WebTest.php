<?php

use App\Models\ProductEvent;
use App\Models\ProductSnapshot;
use App\Models\StockSnapshot;
use App\Models\User;
use App\Models\UserSetting;
use App\Notifications\ProductAlert;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Make;

describe('accounts', function () {
    it('registers a new user and logs them in', function () {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'new@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        expect(User::where('email', 'new@example.test')->exists())->toBeTrue();
    });

    it('logs in with the right password and refuses the wrong one', function () {
        $user = Make::user();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    });

    it('tells a member what they can do, and a connected member more', function () {
        $user = Make::user();

        $member = $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('user.email', $user->email);
        expect($member->json('capabilities'))->toContain('watchlist', 'alerts', 'lucky_points', 'connect_popmart')
            ->not->toContain('claim_tasks', 'auto_lock');

        $account = Make::account($user);
        $connected = $this->actingAs($user)->getJson('/api/me')->assertJsonPath('popmart.account_id', $account->id)->assertJsonPath('popmart.session_valid', true);
        expect($connected->json('capabilities'))->toContain('claim_tasks', 'auto_lock', 'redeem_points');
    });
});

describe('pages', function () {
    it('serves the dashboard with products, collections and derived card data', function () {
        $collection = Make::collection();
        $product = Make::product(['price' => 4980, 'collection_id' => $collection->id, 'theme_id' => $collection->theme_id], ['Owl']);
        $sku = $product->skus->first();

        ProductSnapshot::create(['product_id' => $product->id, 'tags' => [], 'price' => 5580, 'checked_at' => now()->subDay()]);
        ProductEvent::create(['product_id' => $product->id, 'type' => 'price_drop', 'from' => 5580, 'to' => 4980, 'occurred_at' => now()->subHour()]);
        StockSnapshot::create(['sku_id' => $sku->id, 'stock' => 10, 'has_stock' => true, 'checked_at' => now()->subHours(3)]);
        StockSnapshot::create(['sku_id' => $sku->id, 'stock' => 6, 'has_stock' => true, 'checked_at' => now()->subHour()]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('products', 1)
            ->has('collections', 1)
            ->where('products.0.id', $product->id)
            ->where('products.0.price', 4980)
            ->where('products.0.previous_price', 5580)
            ->where('products.0.last_change.type', 'price_drop')
            ->has('products.0.stock_history', 2)
            ->where('products.0.stock_history.1.stock', 6)
            ->has('products.0.skus', 1)
            ->where('collections.0.product_count', 1)
            ->where('events.0.type', 'price_drop')
            ->where('lucky_points', null));
    });

    it('keeps the account pages for logged-in users and fills them', function () {
        $user = Make::user();
        $pinned = Make::product();
        $other = Make::product();

        $this->actingAs($user)->postJson('/api/pins', ['type' => 'product', 'id' => $pinned->id])->assertOk();

        $this->actingAs($user)->get('/watchlist')->assertInertia(fn (Assert $page) => $page
            ->component('Watchlist')
            ->has('products', 1)
            ->where('products.0.id', $pinned->id)
            ->where('lists.pinned_product_ids', [$pinned->id]));

        $this->actingAs($user)->get('/alerts')->assertInertia(fn (Assert $page) => $page
            ->component('Alerts')
            ->where('settings.email', false)
            ->has('settings.available_types'));
    });
});

describe('pins and watches', function () {
    it('toggles a product pin and a collection watch, returning the lists', function () {
        $user = Make::user();
        $product = Make::product();
        $collection = Make::collection();

        $this->actingAs($user)->postJson('/api/pins', ['type' => 'product', 'id' => $product->id])->assertOk()->assertJsonPath('pinned_product_ids', [$product->id]);
        $this->actingAs($user)->postJson('/api/pins', ['type' => 'product', 'id' => $product->id])->assertOk()->assertJsonPath('pinned_product_ids', []);

        $this->actingAs($user)->postJson('/api/watches', ['type' => 'collection', 'id' => $collection->id])->assertOk()->assertJsonPath('watched_collection_ids', [$collection->id]);
    });

    it('rejects unknown ids and kinds', function () {
        $user = Make::user();

        $this->actingAs($user)->postJson('/api/pins', ['type' => 'product', 'id' => 'nope'])->assertNotFound();
        $this->actingAs($user)->postJson('/api/pins', ['type' => 'banana', 'id' => 'x'])->assertUnprocessable();
    });

    it('keeps each user\'s lists to themselves', function () {
        $first = Make::user();
        $second = Make::user();
        $product = Make::product();

        $this->actingAs($first)->postJson('/api/watches', ['type' => 'product', 'id' => $product->id])->assertOk();

        $this->actingAs($second)->getJson('/api/me');
        $this->actingAs($second)->get('/watchlist')->assertInertia(fn (Assert $page) => $page->has('products', 0)->where('lists.watched_product_ids', []));
    });
});

describe('who may act on a Pop Mart account', function () {
    it('answers another user\'s account and a missing one the same', function () {
        $owner = Make::user();
        $account = Make::account($owner);
        $intruder = Make::user();
        Make::account($intruder);

        $theirs = $this->actingAs($intruder)->postJson("/api/popmart-accounts/{$account->id}/claim-tasks")->assertForbidden();
        $missing = $this->actingAs($intruder)->postJson('/api/popmart-accounts/999999/claim-tasks')->assertForbidden();

        expect($missing->json())->toBe($theirs->json())
            ->and($theirs->json('error.code'))->toBe('forbidden');
    });

    it('refuses the owner when the session has expired, without calling Pop Mart', function () {
        $user = Make::user();
        $account = Make::account($user, live: false);

        $this->actingAs($user)->postJson("/api/popmart-accounts/{$account->id}/claim-tasks")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'popmart_session_expired');
    });

    it('refuses a member with no connected account the Pop Mart actions', function () {
        $this->actingAs(Make::user())->getJson('/api/auto-lock')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'popmart_not_connected');
    });
});

describe('alerts API', function () {
    it('lists, counts and marks alerts read', function () {
        $user = Make::user();
        $product = Make::product();
        $event = ProductEvent::create(['product_id' => $product->id, 'type' => 'restocked', 'from' => 0, 'to' => 48, 'occurred_at' => now()]);
        $user->notifyNow(new ProductAlert($event));

        $this->actingAs($user)->getJson('/api/alerts')->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('alerts.0.kind', 'product')
            ->assertJsonPath('alerts.0.event_type', 'restocked');

        $this->actingAs($user)->postJson('/api/alerts/read')->assertOk()->assertJsonPath('unread', 0);
    });

    it('stores alert settings and rejects ones it cannot honour', function () {
        $user = Make::user();

        $this->actingAs($user)->getJson('/api/alert-settings')->assertOk()->assertJsonPath('email', false)->assertJsonPath('telegram', false);

        $this->actingAs($user)->putJson('/api/alert-settings', ['types' => ['restocked', 'sold_out'], 'email' => true, 'telegram' => false])
            ->assertOk()->assertJsonPath('types', ['restocked', 'sold_out'])->assertJsonPath('email', true);
        expect(UserSetting::where('user_id', $user->id)->first()->preferences['alerts']['email'])->toBeTrue();

        // Tag changes are feed-only, and Telegram needs a chat id.
        $this->actingAs($user)->putJson('/api/alert-settings', ['types' => ['tag_added'], 'email' => false, 'telegram' => false])->assertUnprocessable();
        $this->actingAs($user)->putJson('/api/alert-settings', ['types' => ['restocked'], 'email' => false, 'telegram' => true])->assertUnprocessable();
    });
});
