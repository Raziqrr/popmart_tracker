<?php

use App\Models\AutoLockRule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Make;

test('pop-now page renders for guests with draw products and sets', function () {
    $product = Make::product(['business_type' => 'draw', 'is_coming_soon' => false], ['Figure 1', 'Figure 2']);
    [$set, $boxes] = Make::set($product, 5, '01');

    $this->get('/pop-now')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PopNow')
            ->has('products')
            ->where('selected_product_id', $product->id)
            ->has('sets', 1)
            ->where('sets.0.set.set_no', '01')
            ->where('sets.0.boxes.0.box_no', '01')
            ->where('sets.0.boxes.0.state', 'available'));
});

test('pop-now page accepts product query param to select a specific draw', function () {
    $drawA = Make::product(['business_type' => 'draw']);
    $drawB = Make::product(['business_type' => 'draw']);

    $this->get("/pop-now?product={$drawB->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PopNow')
            ->where('selected_product_id', $drawB->id));
});

test('auto-lock page renders for authenticated user with rules and attempts', function () {
    $user = User::factory()->create();
    $account = Make::account($user, live: true, attributes: ['name' => 'Test Popmart Member']);
    $product = Make::product(['business_type' => 'draw']);

    $rule = AutoLockRule::create([
        'user_id' => $user->id,
        'popmart_account_id' => $account->id,
        'product_id' => $product->id,
        'target_kind' => 'random',
        'count' => 1,
        'min_confirmations' => 1,
        'verified_only' => false,
        'trigger' => 'sale_opens',
        'lock_duration_seconds' => 300,
        'checkout_immediately' => true,
        'enabled' => true,
        'acknowledged_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/auto-lock')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AutoLock')
            ->has('products')
            ->has('rules', 1)
            ->where('rules.0.id', (string) $rule->id)
            ->has('attempts')
            ->has('limits')
            ->where('account.label', $account->popmart_member_id)
            ->where('account.session_valid', true));
});

test('auto-lock page renders setup mode when query param is passed', function () {
    $user = User::factory()->create();
    $product = Make::product(['business_type' => 'draw']);

    $this->actingAs($user)
        ->get("/auto-lock?mode=setup&product={$product->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AutoLock'));
});
