<?php

use App\Models\Collection;
use App\Models\Product;
use App\Models\Sku;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\Adapters\SkuStockAdapter;
use App\Services\PopMart\Sync\Adapters\SpuAdapter;
use App\Services\PopMart\Sync\CollectionDiscovery;
use App\Services\PopMart\Sync\PopMartIds;
use App\Services\PopMart\Sync\ProductWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('real data snapshot sync', function () {
    it('maps real FindSummariesByCollectionId snapshot payload', function () {
        $sample = json_decode(file_get_contents(base_path('tests/Fixtures/popmart/FindSummariesByCollectionId.json')), true);
        $products = SpuAdapter::mapList($sample['response']['json'], Country::Malaysia);

        expect($products)->toHaveCount(20);

        $first = $products[0];
        expect($first['id'])->toBe('85af6ee4-61b8-4ee8-8c36-791a9bef6ea6')
            ->and($first['name'])->toBe('Bikini Bottom Buddies - Ghost Fish Series Plush Pendant')
            ->and($first['price'])->toBe(5580)
            ->and($first['currency'])->toBe('MYR')
            ->and($first['business_type'])->toBe('shop')
            ->and($first['remain_stock'])->toBe(2)
            ->and($first['tags'])->toBe(['NEW'])
            ->and($first['collection'])->toMatchArray([
                'collection_id' => 'a1893f8d-118c-446b-86f1-bba2bfbdef4e',
                'collection_name' => 'TOP SELLIINGS',
                'theme_id' => 'efc371ce-c7dc-4d71-a820-136113d96587',
                'theme_name' => 'THE MONSTERS',
            ]);
    });

    it('maps real FindByIds snapshot payload with SKU details', function () {
        $sample = json_decode(file_get_contents(base_path('tests/Fixtures/popmart/FindByIds.json')), true);
        $products = SpuAdapter::mapList($sample['response']['json'], Country::Malaysia);

        expect($products)->toHaveCount(1);

        $product = $products[0];
        expect($product['id'])->toBe('85af6ee4-61b8-4ee8-8c36-791a9bef6ea6')
            ->and($product['name'])->toBe('Bikini Bottom Buddies - Ghost Fish Series Plush Pendant')
            ->and($product['skus'])->toHaveCount(1);

        $sku = $product['skus'][0];
        expect($sku['id'])->toBe('cf952aa2-6424-4957-ba65-1de9659dceb7')
            ->and($sku['name'])->toBe('1 Set')
            ->and($sku['bar_code'])->toBe('6923886107059')
            ->and($sku['sku_code'])->toBe('P022606002001');
    });

    it('maps real CheckSkuStock snapshot payload', function () {
        $sample = json_decode(file_get_contents(base_path('tests/Fixtures/popmart/CheckSkuStock.json')), true);
        $stock = SkuStockAdapter::map($sample['response']['json'], 'cf952aa2-6424-4957-ba65-1de9659dceb7');

        expect($stock)->toHaveKey('cf952aa2-6424-4957-ba65-1de9659dceb7')
            ->and($stock['cf952aa2-6424-4957-ba65-1de9659dceb7'])->toBe([
                'stock' => 2,
                'has_stock' => true,
            ]);
    });

    it('extracts candidate UUIDs from real FindLiveWithPagination CMS snapshot', function () {
        $sample = json_decode(file_get_contents(base_path('tests/Fixtures/popmart/FindLiveWithPagination.json')), true);
        $ids = PopMartIds::extract($sample['response']['json']);

        expect($ids)->toBeArray()
            ->and(count($ids))->toBeGreaterThanOrEqual(18)
            ->and($ids)->toContain('f3ffbfb8-1967-47f1-9104-01e24a1b17b2');
    });
});
