<?php

use App\Services\Api\PopMart\Endpoints\Draw\AssignSet;
use App\Services\Api\PopMart\Endpoints\Draw\EnterBox;
use App\Services\Api\PopMart\Endpoints\Ec\CheckSkuStock;
use App\Services\Api\PopMart\PopMartError;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Locator\Country;
use App\Services\PopMart\PayloadInspector;
use App\Services\PopMart\PopMartThrottle;
use App\Services\PopMart\Sync\Adapters\Adapter;
use App\Services\PopMart\Sync\Adapters\LuckyPointsAdapter;
use App\Services\PopMart\Sync\Adapters\PopNowSetAdapter;
use App\Services\PopMart\Sync\Adapters\SkuStockAdapter;
use App\Services\PopMart\Sync\Adapters\SpuAdapter;
use App\Services\PopMart\Sync\PopMartIds;
use App\Services\PopMart\Sync\ProductChangeDetector;
use App\Services\PopMart\Sync\ProductEventType;
use App\Services\PopMart\Sync\SellRate;
use App\Services\PopMart\Sync\SyncTier;
use App\Services\PopMart\Sync\UnexpectedPayload;
use App\Services\PopMart\ThrottleLane;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/*
| The sync's pure parts, no database. Adapter samples marked "assumed" use the
| guessed field names (adapters with CONFIRMED = false) and should be replaced
| with real responses from popmart:capture.
*/

beforeEach(function () {
    config(['apis.popmart.log_calls' => false, 'apis.popmart.record_fields' => false]);
});

function eventTypes(array $events): array
{
    return array_map(fn ($event) => $event['type'], $events);
}

function state(array $overrides = []): array
{
    return ['tags' => [], 'price' => 5580, 'sale_start_at' => null, 'sale_mode' => 'regular', ...$overrides];
}

describe('change detection', function () {
    it('lists a product it has never seen, and nothing else', function () {
        expect(eventTypes((new ProductChangeDetector)->productChanges(null, state(['tags' => ['new']]))))
            ->toBe([ProductEventType::Listed]);
    });

    it('opens the sale when COMING_SOON goes away', function () {
        $events = (new ProductChangeDetector)->productChanges(state(['tags' => ['COMING_SOON']]), state());

        expect(eventTypes($events))->toBe([ProductEventType::SaleStarted, ProductEventType::TagRemoved]);
    });

    it('catches a sale time appearing or moving', function () {
        $detector = new ProductChangeDetector;
        $at = Carbon::parse('2026-10-05 10:00:00');

        expect(eventTypes($detector->productChanges(state(), state(['sale_start_at' => $at]))))->toBe([ProductEventType::SaleTimeChanged])
            ->and(eventTypes($detector->productChanges(state(['sale_start_at' => $at]), state(['sale_start_at' => $at->copy()]))))->toBe([])
            ->and(eventTypes($detector->productChanges(state(['sale_start_at' => $at]), state(['sale_start_at' => $at->copy()->addHour()]))))->toBe([ProductEventType::SaleTimeChanged]);
    });

    it('records price moves and never-seen tags', function () {
        $events = (new ProductChangeDetector)->productChanges(state(), state(['price' => 4980, 'tags' => ['EARLY_BIRD']]));

        expect(eventTypes($events))->toBe([ProductEventType::PriceDrop, ProductEventType::TagAdded])
            ->and($events[1]['to'])->toBe('EARLY_BIRD');
    });

    it('keeps pre-order apart from release', function () {
        $detector = new ProductChangeDetector;

        expect(eventTypes($detector->productChanges(state(), state(['sale_mode' => 'preorder']))))->toBe([ProductEventType::PreorderOpened])
            ->and(eventTypes($detector->productChanges(state(['sale_mode' => 'preorder']), state())))->toBe([ProductEventType::PreorderClosed]);
    });

    it('turns stock changes into events', function (?array $before, array $after, array $expected) {
        expect(eventTypes((new ProductChangeDetector)->stockChanges($before, $after, lowStock: 10)))->toBe($expected);
    })->with([
        'first check' => [null, ['stock' => 5, 'has_stock' => true], []],
        'restock' => [['stock' => 0, 'has_stock' => false], ['stock' => 40, 'has_stock' => true], [ProductEventType::Restocked]],
        'sold out' => [['stock' => 3, 'has_stock' => true], ['stock' => 0, 'has_stock' => false], [ProductEventType::SoldOut]],
        'falls below low stock' => [['stock' => 15, 'has_stock' => true], ['stock' => 8, 'has_stock' => true], [ProductEventType::LowStock]],
        'already low' => [['stock' => 8, 'has_stock' => true], ['stock' => 6, 'has_stock' => true], []],
    ]);

    it('only snapshots stock when it changed', function () {
        expect(ProductChangeDetector::stockChanged(null, ['stock' => 1, 'has_stock' => true]))->toBeTrue()
            ->and(ProductChangeDetector::stockChanged(['stock' => 1, 'has_stock' => true], ['stock' => 1, 'has_stock' => true]))->toBeFalse()
            ->and(ProductChangeDetector::stockChanged(['stock' => 1, 'has_stock' => true], ['stock' => 0, 'has_stock' => true]))->toBeTrue();
    });
});

describe('sell rate', function () {
    $at = fn (int $hoursAgo, int $stock, bool $hasStock = true) => [
        'checked_at' => Carbon::parse('2026-10-03 12:00:00')->subHours($hoursAgo), 'stock' => $stock, 'has_stock' => $hasStock,
    ];
    $now = Carbon::parse('2026-10-03 12:00:00');
    $windowStart = $now->copy()->subHours(6);

    it('counts drops over the window, starting from the stock before it', function () use ($at, $now, $windowStart) {
        // 100 before the window, then 88, then 76: 24 sold over the 6 hour window.
        expect(SellRate::perHour([$at(9, 100), $at(4, 88), $at(1, 76)], $windowStart, $now))->toBe(4.0);
    });

    it('ignores restocks instead of letting them cancel sales', function () use ($at, $now, $windowStart) {
        // 50 → 20 (30 sold), restock to 80, → 74 (6 sold): 36 over 6 hours.
        expect(SellRate::perHour([$at(7, 50), $at(5, 20), $at(3, 80), $at(1, 74)], $windowStart, $now))->toBe(6.0);
    });

    it('reports 0 when nothing moved, and nothing when history is too short', function () use ($at, $now, $windowStart) {
        expect(SellRate::perHour([$at(10, 40)], $windowStart, $now))->toBe(0.0)
            ->and(SellRate::perHour([['checked_at' => $now->copy()->subMinutes(10), 'stock' => 5, 'has_stock' => true]], $windowStart, $now))->toBeNull()
            ->and(SellRate::perHour([], $windowStart, $now))->toBeNull();
    });

    it('has no rate for masked stock', function () use ($at, $now, $windowStart) {
        expect(SellRate::perHour([$at(8, 0, true), $at(2, 0, false)], $windowStart, $now))->toBeNull();
    });

    it('estimates hours to sell out', function () {
        expect(SellRate::hoursToSellOut(30, 6.0))->toBe(5.0)
            ->and(SellRate::hoursToSellOut(30, 0.0))->toBeNull()
            ->and(SellRate::hoursToSellOut(30, null))->toBeNull();
    });
});

describe('polling tiers', function () {
    // Plain data: times are minutes from now (negative = past), built into dates in the test.
    it('decides each product\'s tier', function (array $input, SyncTier $expected) {
        $now = Carbon::parse('2026-10-02 12:00:00');
        $minutes = fn (?int $offset) => $offset === null ? null : $now->copy()->addMinutes($offset);

        $args = [
            'watched' => $input['watched'] ?? false,
            'preorder' => $input['preorder'] ?? false,
            'saleStartAt' => $minutes($input['saleStart'] ?? null),
            'hotUntil' => $minutes($input['hotUntil'] ?? null),
            'soldOut' => $input['soldOut'] ?? false,
            'lastStockChangeAt' => $minutes($input['lastStockChange'] ?? -24 * 60),
            'lastSeenAt' => $minutes($input['lastSeen'] ?? 0),
            'now' => $now,
        ];

        expect(SyncTier::decide(...$args))->toBe($expected);
    })->with([
        'plain product' => [[], SyncTier::Normal],
        'watched' => [['watched' => true], SyncTier::Hot],
        'pre-order' => [['preorder' => true], SyncTier::Hot],
        'sale in an hour' => [['saleStart' => 60], SyncTier::Hot],
        'sale started an hour ago' => [['saleStart' => -60], SyncTier::Hot],
        'sale next week' => [['saleStart' => 7 * 24 * 60], SyncTier::Normal],
        'collection activity' => [['hotUntil' => 30], SyncTier::Hot],
        'sold out and quiet' => [['soldOut' => true, 'lastStockChange' => -10 * 24 * 60], SyncTier::Cold],
        'sold out yesterday' => [['soldOut' => true], SyncTier::Normal],
        'delisted' => [['lastSeen' => -8 * 24 * 60], SyncTier::Cold],
    ]);
});

describe('adapters', function () {
    it('maps a product listing (assumed field names)', function () {
        $products = SpuAdapter::mapList(['list' => [[
            'id' => 'b6a5c2c0-0000-4000-8000-000000000001',
            'title' => 'Pebble Pals Midnight Snack Series',
            'price' => 55.8,
            'tags' => ['COMING_SOON'],
            'saleStartTime' => 1_791_100_800_000,
        ]]], Country::Malaysia);

        expect($products)->toHaveCount(1)
            ->and($products[0]['name'])->toBe('Pebble Pals Midnight Snack Series')
            ->and($products[0]['price'])->toBe(5580)
            ->and($products[0]['currency'])->toBe('MYR')
            ->and($products[0]['business_type'])->toBeNull()
            ->and($products[0]['sale_start_at']->getTimestamp())->toBe(1_791_100_800)
            ->and($products[0]['sale_mode'])->toBe('regular');
    });

    it('reads which collection and theme a product belongs to (assumed field names)', function () {
        expect(SpuAdapter::collectionRef([
            'collectionId' => 'C0A1B2C3-0001-4D5E-8F90-000000000009',
            'collectionName' => 'Pebble Pals',
            'ipId' => 'D0000000-0000-4000-8000-000000000001',
        ]))->toBe([
            'collection_id' => 'c0a1b2c3-0001-4d5e-8f90-000000000009',
            'collection_name' => 'Pebble Pals',
            'theme_id' => 'd0000000-0000-4000-8000-000000000001',
            'theme_name' => null,
        ])
            ->and(SpuAdapter::collectionRef(['title' => 'No collection']))->toBeNull()
            ->and(SpuAdapter::mapProduct(['id' => 'x', 'title' => 'Y', 'collectionId' => 'c1'], Country::Malaysia)['collection']['collection_id'])->toBe('c1');
    });

    it('skips an item it cannot read but keeps the rest', function () {
        $products = SpuAdapter::mapList(['list' => [
            ['id' => 'a1', 'title' => 'Readable'],
            ['title' => 'No id'],
            'not even an object',
        ]], Country::Malaysia);

        expect(array_column($products, 'id'))->toBe(['a1']);
    });

    it('fails loudly when no item can be read, since the field names are wrong', function () {
        SpuAdapter::mapList(['list' => [['title' => 'No id'], ['name' => 'Also no id']]], Country::Malaysia);
    })->throws(UnexpectedPayload::class, 'spu id');

    it('refuses to guess when an item has no id', function () {
        SpuAdapter::mapList([['title' => 'No id']], Country::Malaysia);
    })->throws(UnexpectedPayload::class, 'spu id');

    it('converts prices and times', function () {
        expect(Adapter::toMinorUnits(5580))->toBe(5580)
            ->and(Adapter::toMinorUnits('55.80'))->toBe(5580)
            ->and(Adapter::toMinorUnits(null))->toBeNull()
            ->and(Adapter::toTime(1_791_100_800)->getTimestamp())->toBe(1_791_100_800)
            ->and(Adapter::toTime('2026-10-05T10:00:00Z')->toIso8601ZuluString())->toBe('2026-10-05T10:00:00Z');
    });

    it('maps stock, including masked products that only say in or out (assumed field names)', function () {
        expect(SkuStockAdapter::map([['skuId' => 's1', 'stock' => 12], ['skuId' => 's2', 'hasStock' => false]]))->toBe([
            's1' => ['stock' => 12, 'has_stock' => true],
            's2' => ['stock' => 0, 'has_stock' => false],
        ]);
    });

    it('reads the lucky points gauge (assumed field names)', function () {
        expect(LuckyPointsAdapter::map(['tasks' => [], 'gauge' => ['current' => 2480, 'max' => 500]]))->toBe([
            'balance' => 2480, 'redeem_minimum' => 500, 'expiring_points' => null, 'expiring_at' => null,
        ]);
    });

    it('stores nothing when the tasks response has no points', function () {
        LuckyPointsAdapter::map(['tasks' => [], 'userTasks' => []]);
    })->throws(UnexpectedPayload::class, 'lucky points balance');

    it('maps an AssignSet grid (confirmed shape)', function () {
        $set = PopNowSetAdapter::mapSet(['setNo' => 'S-1042', 'boxes' => [
            ['position' => 1, 'boxNo' => '07', 'status' => 'available', 'isLocked' => false],
            ['position' => 2, 'boxNo' => '08', 'status' => 'available', 'isLocked' => true],
        ]]);

        expect($set['set_no'])->toBe('S-1042')
            ->and($set['boxes'][1])->toBe(['box_no' => '08', 'position' => 2, 'status' => 'available', 'is_locked' => true]);
    });
});

it('finds Pop Mart ids anywhere in a CMS payload', function () {
    $ids = PopMartIds::extract(['banners' => [['link' => '/products/B6A5C2C0-0000-4000-8000-000000000001?x=1'], ['spuId' => 'b6a5c2c0-0000-4000-8000-000000000001']]]);

    expect($ids)->toBe(['b6a5c2c0-0000-4000-8000-000000000001']);
});

it('flattens a response into field paths', function () {
    expect(array_keys(PayloadInspector::paths(['json' => ['list' => [['id' => 'a', 'tags' => ['x']], ['id' => 'b', 'earlyPass' => true]], 'total' => 2]])))
        ->toBe(['json.list[].id', 'json.list[].tags[]', 'json.list[].earlyPass', 'json.total']);
});

describe('rate limiting', function () {
    it('caps background calls below the shared budget, leaving room for users', function () {
        config(['apis.popmart.calls_per_minute' => 3, 'apis.popmart.background_per_minute' => 2]);
        $throttle = app(PopMartThrottle::class);

        $throttle->acquire(ThrottleLane::Background);
        $throttle->acquire(ThrottleLane::Background);

        expect(fn () => $throttle->acquire(ThrottleLane::Background))->toThrow(PopMartException::class);
        $throttle->acquire(ThrottleLane::User);
        expect(fn () => $throttle->acquire(ThrottleLane::User))->toThrow(PopMartException::class);
    });

    it('pauses every call after Pop Mart answers 429', function () {
        Http::fake(['*' => Http::response(null, 429, ['Retry-After' => '45'])]);

        expect(fn () => (new CheckSkuStock)->data(['spuId' => 'x']))->toThrow(PopMartException::class);
        expect(app(PopMartThrottle::class)->pausedFor())->toBeGreaterThan(40);

        // The next call doesn't even reach Pop Mart.
        $error = null;
        try {
            (new CheckSkuStock)->data(['spuId' => 'x']);
        } catch (PopMartException $e) {
            $error = $e;
        }
        expect($error?->error)->toBe(PopMartError::RateLimited);
        Http::assertSentCount(1);
    });
});

describe('house account', function () {
    it('never sends an endpoint that holds boxes', function () {
        Http::fake();

        expect(fn () => (new EnterBox)->withSession('house-cookie')->asHouse()->data(['spuId' => 'x', 'setNo' => '1', 'boxNos' => ['07']]))
            ->toThrow(RuntimeException::class, 'never call it with the house account');
        Http::assertNothingSent();
    });

    it('may poll AssignSet, from the background budget', function () {
        Http::fake(['*' => Http::response(['json' => ['setNo' => 'S-1', 'boxes' => []]])]);
        config(['apis.popmart.background_per_minute' => 1]);

        (new AssignSet)->withSession('house-cookie')->asHouse()->data(['spuId' => 'x']);

        // That used the whole background share: the next house call is refused.
        expect(fn () => (new AssignSet)->withSession('house-cookie')->asHouse()->data(['spuId' => 'x']))->toThrow(PopMartException::class);
    });
});
