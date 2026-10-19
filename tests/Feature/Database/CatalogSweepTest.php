<?php

use App\Models\Collection;
use App\Models\Product;
use App\Models\Theme;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\CatalogSweep;
use App\Services\PopMart\Sync\CollectionDiscovery;
use Illuminate\Support\Facades\Http;
use Tests\Support\Make;

const SWEEP_PRODUCT = 'aaaaaaaa-0000-4000-8000-000000000001';
const SWEEP_NEW_PRODUCT = 'aaaaaaaa-0000-4000-8000-000000000002';
const SWEEP_NEW_COLLECTION = 'cccccccc-0000-4000-8000-000000000001';
const SWEEP_NEW_THEME = 'dddddddd-0000-4000-8000-000000000001';

function sweepItem(string $id, string $title, string $collectionId, array $extra = []): array
{
    return [
        'id' => $id,
        'title' => $title,
        'price' => 55.8,
        'tags' => ['COMING_SOON'],
        'collectionId' => $collectionId,
        'skus' => [['skuId' => substr_replace($id, 'bb', 0, 2), 'title' => 'Owl']],
        ...$extra,
    ];
}

it('finds new products, then a new collection through the CMS, and heats the collection', function () {
    $known = Make::collection();

    Http::fake(function ($request) use ($known) {
        $url = $request->url();
        $collectionId = data_get($request->data(), 'json.collectionId');
        $empty = Http::response(['json' => ['list' => []]]);

        return match (true) {
            str_contains($url, 'FindSummariesByCollectionId') && $collectionId === $known->id => Http::response(['json' => ['list' => [sweepItem(SWEEP_PRODUCT, 'Known drop', $known->id)]]]),
            str_contains($url, 'FindSummariesByCollectionId') && $collectionId === SWEEP_NEW_COLLECTION => Http::response(['json' => ['list' => [
                sweepItem(SWEEP_NEW_PRODUCT, 'Surprise drop', SWEEP_NEW_COLLECTION, ['collectionName' => 'Surprise Series', 'ipId' => SWEEP_NEW_THEME, 'ipName' => 'Surprise']),
            ]]]),
            str_contains($url, 'FindSummariesByCollectionId'), str_contains($url, 'FindDrawSpusByCollectionId'), str_contains($url, 'FindByIds') => $empty,
            // The live CMS content mentions an ID we know nothing about.
            str_contains($url, 'findLiveWithPagination') => Http::response(['json' => ['banners' => [['spuId' => SWEEP_NEW_COLLECTION]]]]),
            default => Http::response('{}', 404),
        };
    });

    $report = app(CatalogSweep::class)->run();

    expect($report['stopped'])->toBeNull()
        ->and($report['new_collections'])->toBe(1);

    $new = Collection::find(SWEEP_NEW_COLLECTION);
    expect($new)->not->toBeNull()
        ->and($new->name)->toBe('Surprise Series')
        ->and($new->name_confirmed)->toBeTrue()
        ->and($new->source)->toBe('cms')
        ->and($new->theme_id)->toBe(SWEEP_NEW_THEME)
        ->and(Product::find(SWEEP_NEW_PRODUCT)->collection_id)->toBe(SWEEP_NEW_COLLECTION)
        ->and(Product::find(SWEEP_PRODUCT)->collection_id)->toBe($known->id)
        ->and($known->fresh()->last_swept_at)->not->toBeNull();

    // A listing event means more may be coming: the collection's products poll hot for a while.
    expect(Product::find(SWEEP_PRODUCT)->hot_until->isFuture())->toBeTrue();
});

it('stops quietly when Pop Mart pauses us, without throwing', function () {
    Make::collection();
    Http::fake(['*' => Http::response(null, 429, ['Retry-After' => '30'])]);

    $report = app(CatalogSweep::class)->run();

    expect($report['stopped'])->not->toBeNull()
        ->and($report['products'])->toBe(0);
});

it('does not ask again about an id that turned out to be neither a product nor a collection', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, 'findLiveWithPagination') => Http::response(['json' => ['banners' => [['image' => SWEEP_NEW_COLLECTION]]]]),
            default => Http::response(['json' => ['list' => []]]),
        };
    });

    app(CatalogSweep::class)->run();
    $callsAfterFirst = count(Http::recorded());

    app(CatalogSweep::class)->run();

    // The second run repeats the CMS read only: the id isn't probed again for a day.
    expect(count(Http::recorded()) - $callsAfterFirst)->toBe(1);
});

describe('collection discovery', function () {
    it('creates a collection and theme with placeholders, then fills in the real names', function () {
        $discovery = app(CollectionDiscovery::class);

        $collection = $discovery->ensure(SWEEP_NEW_COLLECTION, Country::Malaysia, 'product', null, SWEEP_NEW_THEME, null);

        expect($collection->name)->toBe('Collection cccccccc')
            ->and($collection->name_confirmed)->toBeFalse()
            ->and(Theme::find(SWEEP_NEW_THEME)->name_confirmed)->toBeFalse();

        $discovery->ensure(SWEEP_NEW_COLLECTION, Country::Malaysia, 'product', 'Surprise Series', SWEEP_NEW_THEME, 'Surprise');

        expect($collection->fresh()->name)->toBe('Surprise Series')
            ->and($collection->fresh()->name_confirmed)->toBeTrue()
            ->and(Theme::find(SWEEP_NEW_THEME)->name)->toBe('Surprise');
    });

    it('survives two themes with the same name', function () {
        $discovery = app(CollectionDiscovery::class);

        $a = $discovery->ensure('cccccccc-0000-4000-8000-00000000000a', Country::Malaysia, 'product', 'Same Name', 'dddddddd-0000-4000-8000-00000000000a', 'Same Name');
        $b = $discovery->ensure('cccccccc-0000-4000-8000-00000000000b', Country::Malaysia, 'product', 'Same Name', 'dddddddd-0000-4000-8000-00000000000b', 'Same Name');

        expect($a->theme->slug)->not->toBe($b->theme->slug)
            ->and(Theme::count())->toBe(2);
    });

    it('reuses the collection a theme already has in that area', function () {
        $existing = Make::collection();

        $found = app(CollectionDiscovery::class)->ensure(SWEEP_NEW_COLLECTION, Country::Malaysia, 'cms', null, $existing->theme_id, null);

        expect($found->id)->toBe($existing->id)
            ->and(Collection::count())->toBe(1);
    });
});
