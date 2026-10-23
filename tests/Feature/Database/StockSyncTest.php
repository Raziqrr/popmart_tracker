<?php

use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\StockSnapshot;
use App\Models\WishlistItem;
use App\Services\PopMart\PopMartThrottle;
use App\Services\PopMart\Sync\StockSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\Make;

function stockResponse(string $skuId, int $stock)
{
    return Http::response(['json' => ['list' => [['skuId' => $skuId, 'stock' => $stock]]]]);
}

/** Make the product due again, as if its next check time had arrived. */
function dueAgain(Product $product): void
{
    Product::whereKey($product->id)->update(['next_stock_check_at' => now()->subMinute()]);
}

it('writes a snapshot only when stock changes, and an event when it matters', function () {
    $product = Make::product([], ['Owl']);
    $sku = $product->skus->first();
    Http::fake(['*sku/public_CheckSkuStock*' => Http::sequence()
        ->push(['json' => ['list' => [['skuId' => $sku->id, 'stock' => 12]]]])
        ->push(['json' => ['list' => [['skuId' => $sku->id, 'stock' => 12]]]])
        ->push(['json' => ['list' => [['skuId' => $sku->id, 'stock' => 0]]]])
        ->push(['json' => ['list' => [['skuId' => $sku->id, 'stock' => 40]]]])]);

    $sync = app(StockSync::class);

    // First look: a snapshot, but no event (we don't know what it was before).
    expect($sync->runDue())->toMatchArray(['checked' => 1, 'snapshots' => 1, 'events' => 0, 'stopped' => null]);

    $product->refresh();
    expect($product->sync_tier)->toBe('normal')
        ->and($product->next_stock_check_at->isFuture())->toBeTrue()
        ->and($sku->fresh()->last_checked_at)->not->toBeNull();

    // Same stock: checked, nothing written.
    dueAgain($product);
    expect($sync->runDue())->toMatchArray(['checked' => 1, 'snapshots' => 0, 'events' => 0])
        ->and(StockSnapshot::count())->toBe(1);

    // Gone, then back.
    dueAgain($product);
    $sync->runDue();
    dueAgain($product);
    $sync->runDue();

    expect(StockSnapshot::count())->toBe(3)
        ->and(ProductEvent::where('product_id', $product->id)->orderBy('id')->pluck('type')->map->value->all())->toBe(['sold_out', 'restocked']);
});

it('logs every call and records the response fields it sees', function () {
    $product = Make::product([], ['Owl']);
    Http::fake(['*' => stockResponse($product->skus->first()->id, 5)]);

    app(StockSync::class)->runDue();

    expect(DB::table('popmart_calls')->where('endpoint', 'CheckSkuStock')->where('status', 200)->count())->toBe(1)
        ->and(DB::table('popmart_payload_fields')->where('endpoint', 'CheckSkuStock')->pluck('path')->all())
        ->toContain('json.list[].skuId', 'json.list[].stock');
});

it('polls watched products at the hot tier', function () {
    $product = Make::product([], ['Owl']);
    WishlistItem::create(['user_id' => Make::user()->id, 'watchable_type' => $product->getMorphClass(), 'watchable_id' => $product->id]);
    Http::fake(['*' => stockResponse($product->skus->first()->id, 5)]);

    app(StockSync::class)->runDue();

    $product->refresh();
    expect($product->sync_tier)->toBe('hot')
        ->and($product->next_stock_check_at->lt(now()->addMinutes(3)))->toBeTrue();
});

it('stops the whole run when Pop Mart says slow down', function () {
    Make::product([], ['Owl']);
    Make::product([], ['Toast']);
    Http::fake(['*' => Http::response(null, 429, ['Retry-After' => '30'])]);

    $report = app(StockSync::class)->runDue();

    expect($report['stopped'])->not->toBeNull()
        ->and($report['checked'])->toBe(0)
        ->and(app(PopMartThrottle::class)->pausedFor())->toBeGreaterThan(20);

    // Paused: the next run doesn't reach Pop Mart at all.
    app(StockSync::class)->runDue();
    Http::assertSentCount(1);
});

it('leaves a product due when its stock response cannot be read', function () {
    $product = Make::product([], ['Owl']);
    Http::fake(['*' => Http::response(['json' => ['list' => [['no' => 'skuId']]]])]);

    $report = app(StockSync::class)->runDue();

    expect($report['checked'])->toBe(0)
        ->and($report['skipped'])->toBe(1)
        ->and(StockSnapshot::count())->toBe(0)
        // Rescheduled rather than retried every minute.
        ->and($product->fresh()->next_stock_check_at->isFuture())->toBeTrue();
});
