<?php

use App\Models\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Make;

it('creates every table the app uses', function () {
    $tables = [
        'users', 'themes', 'collections', 'products', 'skus', 'stock_snapshots', 'product_snapshots', 'product_events',
        'popmart_accounts', 'popmart_account_points', 'popmart_actions', 'popmart_calls', 'popmart_payload_fields',
        'pop_now_sets', 'pop_now_boxes', 'pop_now_box_hints', 'pop_now_box_reveals', 'user_checkouts', 'user_checkout_boxes',
        'auto_lock_rules', 'auto_lock_rule_picks', 'auto_lock_attempts', 'wishlist_items', 'pinned_items', 'user_settings', 'notifications',
    ];

    expect(array_values(array_filter($tables, fn ($table) => ! Schema::hasTable($table))))->toBe([]);
});

it('derives the tag flags from products.tags', function () {
    $product = Make::product(['tags' => ['COMING_SOON', 'new']])->fresh();

    expect($product->is_coming_soon)->toBeTrue()
        ->and($product->is_new)->toBeTrue()
        ->and($product->is_sold_out)->toBeFalse();

    $product->update(['tags' => ['OUT_OF_STOCK']]);
    $product = $product->fresh();

    expect($product->is_sold_out)->toBeTrue()
        ->and($product->is_coming_soon)->toBeFalse();
});

it('lets a collection exist before its theme is known', function () {
    $collection = Collection::create(['id' => 'c0a1b2c3-0001-4d5e-8f90-0000000000aa', 'theme_id' => null, 'area' => 'MY', 'name' => 'Collection c0a1b2c3', 'slug' => 'collection-c0a1b2c3', 'name_confirmed' => false]);

    expect($collection->fresh()->theme_id)->toBeNull()
        ->and($collection->fresh()->name_confirmed)->toBeFalse();
});
