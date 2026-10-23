<?php

use App\Models\Collection;
use App\Models\Product;
use App\Models\Sku;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\Adapters\SpuAdapter;
use App\Services\PopMart\Sync\CollectionDiscovery;
use App\Services\PopMart\Sync\ProductWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists real Pop Mart product and collection snapshots to the database', function () {
    $sample = json_decode(file_get_contents(base_path('tests/Fixtures/popmart/FindSummariesByCollectionId.json')), true);
    $products = SpuAdapter::mapList($sample['response']['json'], Country::Malaysia);

    $discovery = app(CollectionDiscovery::class);
    $writer = app(ProductWriter::class);

    $first = $products[0];
    $collection = $discovery->fromProduct($first, Country::Malaysia);

    expect($collection->name)->toBe('TOP SELLIINGS')
        ->and($collection->theme->name)->toBe('THE MONSTERS');

    // Detail payload with SKU information
    $detailSample = json_decode(file_get_contents(base_path('tests/Fixtures/popmart/FindByIds.json')), true);
    $detailProducts = SpuAdapter::mapList($detailSample['response']['json'], Country::Malaysia);
    $detailed = $detailProducts[0];

    $events = $writer->write($detailed, $collection);

    expect($events)->toContain(\App\Services\PopMart\Sync\ProductEventType::Listed);

    $product = Product::find($detailed['id']);
    expect($product)->not->toBeNull()
        ->and($product->id)->toBe('85af6ee4-61b8-4ee8-8c36-791a9bef6ea6')
        ->and($product->name)->toBe('Bikini Bottom Buddies - Ghost Fish Series Plush Pendant')
        ->and($product->price)->toBe(5580)
        ->and($product->collection_id)->toBe($collection->id);

    $savedSku = Sku::where('product_id', $product->id)->first();
    expect($savedSku)->not->toBeNull()
        ->and($savedSku->id)->toBe('cf952aa2-6424-4957-ba65-1de9659dceb7')
        ->and($savedSku->bar_code)->toBe('6923886107059');
});
