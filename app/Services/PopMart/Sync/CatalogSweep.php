<?php

namespace App\Services\PopMart\Sync;

use App\Models\Collection;
use App\Models\Product;
use App\Services\Api\PopMart\Endpoints\Cms\FindLiveWithPagination;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\Adapters\SpuAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The discovery sweep (popmart:sweep, every few minutes): finds new products
 * and state changes, including unannounced drops and pre-orders.
 *
 * 1. Every known collection's listings: new products, tag/price/sale-time changes.
 *    Any activity bumps that whole collection to the hot stock tier.
 * 2. Pop Mart's live CMS content (banners, campaigns): any product ID in it we
 *    don't know yet is looked up. This works without knowing the CMS layout.
 * 3. Products we have but without SKUs get their details (FindByIds).
 *
 * Stops early when the call budget runs out; the next run carries on.
 */
class CatalogSweep
{
    private const DETAILS_BATCH = 20;

    private const UNRESOLVED_KEY = 'popmart:cms:unresolved';

    public function __construct(private ProductWriter $writer) {}

    /** @return array{collections: int, products: int, events: int, skipped: int, stopped: ?string} */
    public function run(): array
    {
        $report = ['collections' => 0, 'products' => 0, 'events' => 0, 'skipped' => 0, 'stopped' => null];

        try {
            foreach ($this->collections() as $collection) {
                $this->sweepCollection($collection, $report);
            }

            foreach ($this->areas() as $country) {
                try {
                    $this->sweepCms($country, $report);
                } catch (PopMartException $e) {
                    if ($e->abortsBatch()) {
                        throw $e;
                    }
                    Log::warning("Pop Mart sweep: CMS failed for {$country->value}", ['error' => $e->getMessage()]);
                }
            }

            $this->fillMissingDetails($report);
        } catch (PopMartException $e) {
            if (! $e->abortsBatch()) {
                throw $e;
            }
            $report['stopped'] = $e->getMessage();
        }

        return $report;
    }

    private function sweepCollection(Collection $collection, array &$report): void
    {
        $country = Country::tryFrom($collection->area) ?? Country::Malaysia;
        $active = false;

        foreach (SpuAdapter::listingEndpoints() as $endpoint => $businessType) {
            try {
                $json = (new $endpoint($country))->data(SpuAdapter::collectionRequest($collection->id));
                $products = SpuAdapter::mapList($json, $country, $businessType);
            } catch (PopMartException $e) {
                if ($e->abortsBatch()) {
                    throw $e;
                }
                Log::warning("Pop Mart sweep: {$endpoint} failed for collection {$collection->id}", ['error' => $e->getMessage()]);

                continue;
            } catch (UnexpectedPayload $e) {
                Log::warning('Pop Mart sweep: '.$e->getMessage());
                $report['skipped']++;

                continue;
            }

            $active = $this->writeAll($products, $collection, $report) || $active;
        }

        $report['collections']++;

        if ($active) {
            // Drops come in clusters: watch the rest of this collection closely for a while.
            Product::where('collection_id', $collection->id)->update([
                'hot_until' => now()->addMinutes((int) config('apis.popmart.sync.cluster_hot_minutes', 60)),
                'next_stock_check_at' => now(),
            ]);
        }
    }

    private function sweepCms(Country $country, array &$report): void
    {
        $json = (new FindLiveWithPagination($country))->data(['pageNo' => 1, 'pageSize' => 50]);

        $unresolved = Cache::get(self::UNRESOLVED_KEY, []);
        $candidates = array_diff(PopMartIds::extract($json), array_keys($unresolved));
        $known = Product::whereIn('id', $candidates)->pluck('id')->all();
        $unknown = array_values(array_diff($candidates, $known));

        foreach (array_chunk($unknown, self::DETAILS_BATCH) as $ids) {
            $found = $this->fetchDetails($ids, $country, $report);

            // IDs that aren't products (images, campaigns, collections): don't ask again for a day.
            foreach (array_diff($ids, $found) as $id) {
                $unresolved[$id] = true;
            }
        }

        Cache::put(self::UNRESOLVED_KEY, $unresolved, now()->addDay());
    }

    private function fillMissingDetails(array &$report): void
    {
        $missing = Product::doesntHave('skus')->limit(self::DETAILS_BATCH)->get(['id', 'area_codes', 'collection_id']);

        foreach ($missing->groupBy(fn (Product $p) => $p->area_codes[0] ?? 'MY') as $area => $products) {
            $this->fetchDetails($products->pluck('id')->all(), Country::tryFrom($area) ?? Country::Malaysia, $report);
        }
    }

    /** @return list<string> the ids Pop Mart returned */
    private function fetchDetails(array $ids, Country $country, array &$report): array
    {
        $endpoint = SpuAdapter::detailsEndpoint();

        try {
            $products = SpuAdapter::mapList((new $endpoint($country))->data(SpuAdapter::idsRequest($ids)), $country);
        } catch (UnexpectedPayload $e) {
            Log::warning('Pop Mart sweep: '.$e->getMessage());
            $report['skipped']++;

            return [];
        }

        $existing = Product::whereIn('id', array_column($products, 'id'))->get()->keyBy('id');
        foreach ($products as $product) {
            $this->writeAll([$product], $existing->get($product['id'])?->collection, $report);
        }

        return array_column($products, 'id');
    }

    /** @return bool whether anything happened worth bumping the collection to hot */
    private function writeAll(array $products, ?Collection $collection, array &$report): bool
    {
        $active = false;

        foreach ($products as $product) {
            $events = $this->writer->write($product, $collection);
            $report['products']++;
            $report['events'] += count($events);
            $active = $active || collect($events)->contains(fn (ProductEventType $type) => $type->signalsActivity());
        }

        return $active;
    }

    /** @return iterable<Collection> */
    private function collections(): iterable
    {
        return Collection::whereIn('area', config('apis.popmart.areas', ['MY']))->lazyById();
    }

    /** @return list<Country> */
    private function areas(): array
    {
        return array_values(array_filter(array_map(fn ($area) => Country::tryFrom($area), config('apis.popmart.areas', ['MY']))));
    }
}
