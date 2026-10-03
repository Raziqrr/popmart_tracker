<?php

namespace App\Services\PopMart\Sync\Adapters;

use App\Services\PopMart\Sync\UnexpectedPayload;
use Illuminate\Support\Carbon;

/**
 * One adapter per Pop Mart endpoint the sync reads: how to ask (request())
 * and where the fields are in the answer (map()). Every Pop Mart field name
 * the sync depends on lives in an adapter, so a wrong guess is a one-file fix.
 *
 * CONFIRMED says whether the names were checked against a real response.
 * Run `php artisan popmart:capture <Endpoint> '<payload>' --check` to check
 * one: it saves the raw response and reports missing fields.
 */
abstract class Adapter
{
    public const CONFIRMED = false;

    /** First of $keys present in $data, else $default. */
    protected static function pick(array $data, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    /** Like pick(), but a missing value means we misread the payload: throw instead of guessing. */
    protected static function need(array $data, array $keys, string $field): mixed
    {
        return self::pick($data, $keys) ?? throw UnexpectedPayload::missing(static::class, $field, $keys);
    }

    /**
     * The list of items in a response: the payload itself if it's a list,
     * else the first list found under one of $paths.
     */
    protected static function items(mixed $json, array $paths): array
    {
        if (is_array($json) && array_is_list($json)) {
            return $json;
        }

        foreach ($paths as $path) {
            $list = is_array($json) ? data_get($json, $path) : null;
            if (is_array($list) && array_is_list($list)) {
                return $list;
            }
        }

        throw UnexpectedPayload::missing(static::class, 'list', $paths);
    }

    /**
     * Price in minor units. Decimals (55.8, "55.80") are major units; whole
     * numbers are taken as minor units already (5580 = RM55.80).
     */
    public static function toMinorUnits(mixed $price): ?int
    {
        if ($price === null || $price === '') {
            return null;
        }

        if (is_int($price)) {
            return $price;
        }

        if (is_string($price) && ctype_digit($price)) {
            return (int) $price;
        }

        return (int) round((float) $price * 100);
    }

    /** Epoch milliseconds, epoch seconds or a date string. */
    public static function toTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === 0) {
            return null;
        }

        if (is_numeric($value)) {
            $number = (int) $value;

            return $number > 100_000_000_000 ? Carbon::createFromTimestampMs($number) : Carbon::createFromTimestamp($number);
        }

        return Carbon::parse($value);
    }

    /** @return list<string> */
    protected static function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('strval', array_filter($value, 'is_scalar'))) : [];
    }
}
