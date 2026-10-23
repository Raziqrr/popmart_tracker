<?php

use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\ProductSnapshot;
use App\Models\Sku;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\Adapters\SpuAdapter;
use App\Services\PopMart\Sync\ProductWriter;
use Tests\Support\Make;

const WRITER_PRODUCT = 'b6a5c2c0-0000-4000-8000-0000000000aa';

/** Write one product the way the sweep does, from a Pop Mart style item. @return list<string> event types */
function writeProduct(array $item, $collection = null): array
{
    $data = SpuAdapter::mapProduct(['id' => WRITER_PRODUCT, 'title' => 'Pebble Pals Midnight Snack', ...$item], Country::Malaysia);

    return array_map(fn ($type) => $type->value, app(ProductWriter::class)->write($data, $collection));
}

it('lists a new product with its figures, one event and one snapshot', function () {
    $events = writeProduct([
        'price' => 55.8,
        'tags' => ['COMING_SOON'],
        'skus' => [['skuId' => '11111111-0000-4000-8000-000000000001', 'title' => 'Owl'], ['skuId' => '11111111-0000-4000-8000-000000000002', 'title' => 'Toast']],
    ]);

    $product = Product::find(WRITER_PRODUCT);

    expect($events)->toBe(['listed'])
        ->and($product->price)->toBe(5580)
        ->and($product->is_coming_soon)->toBeTrue()
        ->and(Sku::where('product_id', WRITER_PRODUCT)->pluck('name')->sort()->values()->all())->toBe(['Owl', 'Toast'])
        ->and(ProductSnapshot::count())->toBe(1)
        ->and(ProductEvent::where('type', 'listed')->count())->toBe(1);
});

it('records nothing when nothing changed', function () {
    writeProduct(['price' => 55.8, 'tags' => ['new']]);

    expect(writeProduct(['price' => 55.8, 'tags' => ['new']]))->toBe([])
        ->and(ProductSnapshot::count())->toBe(1)
        ->and(ProductEvent::count())->toBe(1);
});

it('opens the sale when COMING_SOON goes away', function () {
    writeProduct(['price' => 55.8, 'tags' => ['COMING_SOON']]);

    $events = writeProduct(['price' => 55.8, 'tags' => []]);

    expect($events)->toBe(['sale_started', 'tag_removed'])
        ->and(Product::find(WRITER_PRODUCT)->is_coming_soon)->toBeFalse();
});

it('records a price drop and keeps the price when a response has none', function () {
    writeProduct(['price' => 55.8]);

    expect(writeProduct(['price' => 49.8]))->toBe(['price_drop'])
        ->and(Product::find(WRITER_PRODUCT)->price)->toBe(4980);

    // A response without a price must not wipe the one we have.
    expect(writeProduct([]))->toBe([])
        ->and(Product::find(WRITER_PRODUCT)->price)->toBe(4980);
});

it('files a product under its collection and theme', function () {
    $collection = Make::collection();

    writeProduct(['price' => 55.8], $collection);

    $product = Product::find(WRITER_PRODUCT);

    expect($product->collection_id)->toBe($collection->id)
        ->and($product->theme_id)->toBe($collection->theme_id);
});
