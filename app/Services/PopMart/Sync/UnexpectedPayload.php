<?php

namespace App\Services\PopMart\Sync;

use RuntimeException;

/**
 * A Pop Mart response didn't have a field an adapter needs. The sync skips
 * that item rather than writing a guess; popmart:capture --check shows why.
 */
class UnexpectedPayload extends RuntimeException
{
    public static function missing(string $adapter, string $field, array $triedKeys): self
    {
        return new self(sprintf('%s: no %s (tried %s).', class_basename($adapter), $field, implode(', ', $triedKeys)));
    }
}
