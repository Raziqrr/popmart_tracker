<?php

namespace App\Services\PopMart\Sync;

/**
 * Every Pop Mart ID (UUID) anywhere in a payload, whatever its shape. Lets
 * the CMS sweep find products in banners and campaigns without knowing how
 * Pop Mart lays those out.
 */
class PopMartIds
{
    private const UUID = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';

    /** @return list<string> lower-cased, unique */
    public static function extract(mixed $payload): array
    {
        preg_match_all(self::UUID, (string) json_encode($payload), $matches);

        return array_values(array_unique(array_map('strtolower', $matches[0])));
    }
}
