<?php

namespace App\Exceptions;

use RuntimeException;

class ConfigHashMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $key,
        public readonly string $expectedHash,
        public readonly string $actualHash,
        string $message = '',
    ) {
        $msg = $message !== ''
            ? $message
            : "Hash verification failed for config [{$key}]: expected hash {$expectedHash}, got {$actualHash}.";

        parent::__construct($msg);
    }
}
