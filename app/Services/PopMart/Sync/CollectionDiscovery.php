<?php

namespace App\Services\PopMart\Sync;

use App\Models\Collection;
use App\Models\Theme;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\Adapters\SpuAdapter;
use Illuminate\Support\Str;

/**
 * Finds collections we don't know yet. Pop Mart has no "all collections"
 * endpoint we know of, so they're learned from:
 *
 * - product payloads that name their collectionId (and ipId): fromProduct()
 * - IDs in Pop Mart's live CMS content that aren't products: probe() asks
 *   the collection listing endpoint whether each one is a collection
 * - popmart:add-collection, by hand
 *
 * A new collection is swept by CatalogSweep like any other. When Pop Mart
 * doesn't give a name, a placeholder is used (name_confirmed = false) and
 * replaced as soon as a payload carries the real one.
 */
class CollectionDiscovery
{
    /** CMS IDs probed as possible collections per sweep: each probe is one call. */
    public const PROBE_LIMIT = 5;

    /** The collection a product belongs to, created if new. Null when the product doesn't say. */
    public function fromProduct(array $product, Country $area): ?Collection
    {
        $ref = $product['collection'] ?? null;

        return $ref === null ? null : $this->ensure(
            $ref['collection_id'], $area, 'product',
            $ref['collection_name'], $ref['theme_id'], $ref['theme_name'],
        );
    }

    /**
     * Ask Pop Mart whether each ID is a collection: a collection's listing
     * returns products. Stops after PROBE_LIMIT IDs.
     *
     * Returns the collections found, their products (keyed by collection id)
     * and the IDs actually asked about.
     *
     * @return array{collections: list<Collection>, products: array<string, list<array>>, checked: list<string>}
     */
    public function probe(array $ids, Country $area): array
    {
        $result = ['collections' => [], 'products' => [], 'checked' => []];
        $endpoint = array_key_first(SpuAdapter::listingEndpoints());

        foreach (array_slice(array_values($ids), 0, self::PROBE_LIMIT) as $id) {
            $result['checked'][] = $id;

            try {
                $products = SpuAdapter::mapList((new $endpoint($area))->data(SpuAdapter::collectionRequest($id)), $area);
            } catch (UnexpectedPayload) {
                // Not a listing we can read: not (recognisably) a collection.
                continue;
            }

            if ($products === []) {
                continue;
            }

            // Names usually come with the products.
            $ref = collect($products)->pluck('collection')->filter()->first();
            $collection = $this->ensure($id, $area, 'cms', $ref['collection_name'] ?? null, $ref['theme_id'] ?? null, $ref['theme_name'] ?? null);

            $result['collections'][] = $collection;
            $result['products'][$collection->id] = $products;
        }

        return $result;
    }

    /** Create the collection (and its theme) if new; fill in real names over placeholders. */
    public function ensure(string $collectionId, Country $area, string $source, ?string $name = null, ?string $themeId = null, ?string $themeName = null): Collection
    {
        $collectionId = strtolower($collectionId);
        $theme = $themeId ? $this->ensureTheme($themeId, $themeName) : null;
        $collection = Collection::find($collectionId);

        if ($collection === null) {
            // Collections are one per theme and area: reuse that row if the theme already has one here.
            $collection = $theme ? Collection::where('theme_id', $theme->id)->where('area', $area->value)->first() : null;
        }

        if ($collection === null) {
            $hasRealName = $name !== null || ($theme !== null && $theme->name_confirmed);
            $label = $name ?? ($theme?->name_confirmed ? $theme->name : null);

            return Collection::create([
                'id' => $collectionId,
                'theme_id' => $theme?->id,
                'area' => $area->value,
                'name' => $label ?? self::placeholder('Collection', $collectionId),
                'slug' => Str::slug($label ?? 'collection-'.substr($collectionId, 0, 8)),
                'name_confirmed' => $hasRealName,
                'source' => $source,
            ]);
        }

        $updates = [];
        if (! $collection->name_confirmed && ($name !== null || ($theme !== null && $theme->name_confirmed))) {
            $newName = $name ?? $theme->name;
            $updates += ['name' => $newName, 'slug' => Str::slug($newName), 'name_confirmed' => true];
        }
        if ($collection->theme_id === null && $theme !== null) {
            $updates['theme_id'] = $theme->id;
        }
        if ($updates !== []) {
            $collection->update($updates);
        }

        return $collection;
    }

    private function ensureTheme(string $themeId, ?string $name): Theme
    {
        $theme = Theme::find($themeId);

        if ($theme === null) {
            return Theme::create([
                'id' => $themeId,
                'name' => $name ?? self::placeholder('Theme', $themeId),
                'slug' => self::themeSlug($name, $themeId),
                'name_confirmed' => $name !== null,
            ]);
        }

        if (! $theme->name_confirmed && $name !== null) {
            $theme->update(['name' => $name, 'slug' => self::themeSlug($name, $themeId, ignore: $themeId), 'name_confirmed' => true]);
        }

        return $theme;
    }

    /** themes.slug is unique: a taken slug (or no name at all) gets the id's first 8 characters. */
    private static function themeSlug(?string $name, string $themeId, ?string $ignore = null): string
    {
        $slug = $name ? Str::slug($name) : '';
        $taken = $slug === '' || Theme::where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists();

        return $taken ? ($slug === '' ? 'theme' : $slug).'-'.substr($themeId, 0, 8) : $slug;
    }

    private static function placeholder(string $kind, string $id): string
    {
        return "{$kind} ".substr($id, 0, 8);
    }
}
