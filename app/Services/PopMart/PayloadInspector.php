<?php

namespace App\Services\PopMart;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records every field path Pop Mart responses contain, per endpoint, in
 * popmart_payload_fields. Fields we've never seen before are how we find out
 * what Pop Mart changed or added: the pre-order marker, early-pass state,
 * new tags. List items are merged, so `list[].title` is one path however
 * long the list is.
 */
class PayloadInspector
{
    private const MAX_DEPTH = 6;

    /**
     * Store paths not seen before for this endpoint and return them.
     *
     * @return list<string>
     */
    public function record(string $endpoint, mixed $payload): array
    {
        if (! config('apis.popmart.record_fields', true) || ! is_array($payload)) {
            return [];
        }

        $paths = self::paths($payload);
        $cacheKey = "popmart:fields:{$endpoint}";
        $known = Cache::get($cacheKey, []);
        $new = array_diff_key($paths, $known);

        if ($new === []) {
            return [];
        }

        DB::table('popmart_payload_fields')->insertOrIgnore(array_map(
            fn (string $path) => [
                'endpoint' => $endpoint,
                'path' => $path,
                'example' => Str::limit((string) json_encode($paths[$path]), 500),
                'first_seen_at' => now(),
            ],
            array_keys($new),
        ));

        Cache::forever($cacheKey, $known + array_fill_keys(array_keys($paths), true));

        return array_keys($new);
    }

    /**
     * Every leaf path in a payload, mapped to one example value.
     * ['data' => ['list' => [['id' => 'a']]]] → ['data.list[].id' => 'a'].
     *
     * @return array<string, mixed>
     */
    public static function paths(array $payload, string $prefix = '', int $depth = 0): array
    {
        $paths = [];

        foreach ($payload as $key => $value) {
            $path = array_is_list($payload) ? "{$prefix}[]" : ($prefix === '' ? (string) $key : "{$prefix}.{$key}");

            if (is_array($value) && $value !== [] && $depth < self::MAX_DEPTH) {
                $paths += self::paths($value, $path, $depth + 1);
            } elseif (! isset($paths[$path])) {
                $paths[$path] = $value;
            }

            // The first three items of a list are enough to learn its shape.
            if (array_is_list($payload) && $key >= 2) {
                break;
            }
        }

        return $paths;
    }
}
