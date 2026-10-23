<?php

use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\WishlistItem;
use App\Services\Alerts\AlertDispatcher;
use App\Services\PopMart\Sync\ProductEventType;
use Tests\Support\Make;

function watching(User $user, $model): WishlistItem
{
    return WishlistItem::create(['user_id' => $user->id, 'watchable_type' => $model->getMorphClass(), 'watchable_id' => $model->getKey()]);
}

function happened(Product $product, ProductEventType $type, array $attributes = []): ProductEvent
{
    return ProductEvent::create(['product_id' => $product->id, 'type' => $type, 'occurred_at' => now(), ...$attributes]);
}

it('sends a watcher an in-app alert and marks the event and watch as handled', function () {
    $user = Make::user();
    $product = Make::product();
    $watch = watching($user, $product);
    $event = happened($product, ProductEventType::Restocked, ['from' => 0, 'to' => 48]);

    $report = app(AlertDispatcher::class)->run();

    $alert = $user->notifications()->first();

    expect($report)->toMatchArray(['events' => 1, 'alerts' => 1, 'digests' => 0, 'stale' => 0])
        ->and($alert->data['kind'])->toBe('product')
        ->and($alert->data['event_type'])->toBe('restocked')
        ->and($alert->data['title'])->toBe('Restocked')
        ->and($alert->data['product_name'])->toBe($product->name)
        ->and($event->fresh()->alerted_at)->not->toBeNull()
        ->and($watch->fresh()->notified_at)->not->toBeNull();
});

it('tells nobody who is not watching', function () {
    $watcher = Make::user();
    $stranger = Make::user();
    $product = Make::product();
    watching($watcher, Make::product());
    happened($product, ProductEventType::Restocked);

    app(AlertDispatcher::class)->run();

    expect($watcher->notifications()->count())->toBe(0)
        ->and($stranger->notifications()->count())->toBe(0);
});

it('handles each event once', function () {
    $user = Make::user();
    $product = Make::product();
    watching($user, $product);
    happened($product, ProductEventType::Restocked);

    app(AlertDispatcher::class)->run();

    expect(app(AlertDispatcher::class)->run())->toMatchArray(['events' => 0, 'alerts' => 0])
        ->and($user->notifications()->count())->toBe(1);
});

it('does not repeat the same kind of alert for the same product within its cooldown', function () {
    $user = Make::user();
    $product = Make::product();
    watching($user, $product);

    happened($product, ProductEventType::LowStock, ['from' => 20, 'to' => 9]);
    app(AlertDispatcher::class)->run();
    happened($product, ProductEventType::LowStock, ['from' => 9, 'to' => 4]);
    $second = app(AlertDispatcher::class)->run();

    expect($second['alerts'])->toBe(0)
        ->and($user->notifications()->count())->toBe(1);
});

it('marks events from before an outage handled without alerting', function () {
    $user = Make::user();
    $product = Make::product();
    watching($user, $product);
    $event = happened($product, ProductEventType::Restocked, ['occurred_at' => now()->subHours(2)]);

    $report = app(AlertDispatcher::class)->run();

    expect($report)->toMatchArray(['stale' => 1, 'alerts' => 0])
        ->and($event->fresh()->alerted_at)->not->toBeNull()
        ->and($user->notifications()->count())->toBe(0);
});

it('only alerts for the event types a user chose', function () {
    $user = Make::user();
    UserSetting::create(['user_id' => $user->id, 'preferences' => ['alerts' => ['types' => ['sold_out']]]]);
    $product = Make::product();
    watching($user, $product);

    happened($product, ProductEventType::Restocked);
    happened($product, ProductEventType::SoldOut);

    app(AlertDispatcher::class)->run();

    expect($user->notifications()->get()->pluck('data.event_type')->all())->toBe(['sold_out']);
});

it('folds a burst into one digest', function () {
    $user = Make::user();

    foreach (range(1, 6) as $i) {
        $product = Make::product();
        watching($user, $product);
        happened($product, ProductEventType::Restocked);
    }

    $report = app(AlertDispatcher::class)->run();
    $digest = $user->notifications()->first();

    expect($report)->toMatchArray(['alerts' => 0, 'digests' => 1])
        ->and($user->notifications()->count())->toBe(1)
        ->and($digest->data['kind'])->toBe('digest')
        ->and($digest->data['items'])->toHaveCount(6);
});

it('tells collection followers about listings but not stock wobbles', function () {
    $user = Make::user();
    $collection = Make::collection();
    watching($user, $collection);
    $product = Make::product(['collection_id' => $collection->id]);

    happened($product, ProductEventType::SoldOut);
    expect(app(AlertDispatcher::class)->run()['alerts'])->toBe(0);

    happened($product, ProductEventType::Listed);
    expect(app(AlertDispatcher::class)->run()['alerts'])->toBe(1)
        ->and($user->notifications()->first()->data['event_type'])->toBe('listed');
});

it('tells a figure watcher about their figure and about product-wide news', function () {
    $user = Make::user();
    $product = Make::product([], ['Owl', 'Toast']);
    $owl = $product->skus->firstWhere('name', 'Owl');
    $toast = $product->skus->firstWhere('name', 'Toast');
    watching($user, $owl);

    happened($product, ProductEventType::Restocked, ['sku_id' => $toast->id]);
    expect(app(AlertDispatcher::class)->run()['alerts'])->toBe(0);

    happened($product, ProductEventType::Restocked, ['sku_id' => $owl->id]);
    happened($product, ProductEventType::PriceDrop, ['from' => 5580, 'to' => 4980]);

    expect(app(AlertDispatcher::class)->run()['alerts'])->toBe(2);
});
