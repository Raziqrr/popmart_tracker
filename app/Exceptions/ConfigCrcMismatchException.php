<?php

namespace App\Exceptions;

use RuntimeException;

class ConfigCrcMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $key,
        public readonly int|string $expectedCrc,
        public readonly int $actualCrc,
        string $message = '',
    ) {
        $msg = $message !== ''
            ? $message
            : "CRC check failed for config [{$key}]: expected CRC {$expectedCrc}, got {$actualCrc}.";

        parent::__construct($msg);
    }

    public static function forRow(string $key, int $storedCrc, int $actualCrc): self
    {
        return new self(
            $key,
            $storedCrc,
            $actualCrc,
            "Database CRC mismatch for config [{$key}]: stored checksum {$storedCrc} does not match computed checksum {$actualCrc}.",
        );
    }

    public static function forExpected(string $key, int $expectedCrc, int $actualCrc): self
    {
        return new self(
            $key,
            $expectedCrc,
            $actualCrc,
            "Expected CRC mismatch for config [{$key}]: expected checksum {$expectedCrc}, got {$actualCrc}.",
        );
    }
}
