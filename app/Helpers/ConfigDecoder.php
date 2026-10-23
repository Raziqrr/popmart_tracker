<?php

namespace App\Helpers;

use App\Exceptions\ConfigHashMismatchException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Handles decoding of configuration values and SHA-256 hash integrity checks.
 *
 * Supports decoding base64-encoded values (with or without 'base64:' prefix),
 * encrypted values (prefixed with 'enc:'), and standard type casting.
 */
class ConfigDecoder
{
    /**
     * Compute the SHA-256 hash of a string value.
     */
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * Verify whether a given value matches an expected SHA-256 hash.
     */
    public function verifyHash(string $value, ?string $expectedHash): bool
    {
        if ($expectedHash === null || $expectedHash === '') {
            return true;
        }

        return hash_equals(strtolower($expectedHash), strtolower(self::hash($value)));
    }

    /**
     * Decode a configuration value after verifying its SHA-256 hash.
     *
     * @param  string  $value  The raw value from the database
     * @param  string|null  $hash  The stored/expected SHA-256 hash to verify against
     * @param  string|null  $type  The target type to cast the decoded value to
     * @param  string  $key  The config key (used for exception reporting)
     * @return mixed The decoded and type-cast configuration value
     *
     * @throws ConfigHashMismatchException If hash verification fails
     */
    public function decode(string $value, ?string $hash = null, ?string $type = null, string $key = 'config'): mixed
    {
        if ($hash !== null && $hash !== '') {
            $computedHash = self::hash($value);
            if (! hash_equals(strtolower($hash), strtolower($computedHash))) {
                throw new ConfigHashMismatchException($key, $hash, $computedHash);
            }
        }

        $decoded = $this->unpack($value, $type);

        return $this->castValue($decoded, $type);
    }

    /**
     * Unpack an encoded string (base64, encrypted, or raw).
     */
    public function unpack(string $value, ?string $type = null): string
    {
        if (str_starts_with($value, 'enc:')) {
            return Crypt::decryptString(substr($value, 4));
        }

        if (str_starts_with($value, 'base64:')) {
            $decoded = base64_decode(substr($value, 7), strict: true);

            return $decoded !== false ? $decoded : $value;
        }

        if ($type === 'base64') {
            $decoded = base64_decode($value, strict: true);

            return $decoded !== false ? $decoded : $value;
        }

        return $value;
    }

    /**
     * Helper to encode a value for direct storage in the database.
     *
     * @param  mixed  $value  The value to encode
     * @param  string  $driver  'base64', 'enc' (encrypted), or 'plain'
     */
    public static function encode(mixed $value, string $driver = 'base64'): string
    {
        $serialized = is_scalar($value) || $value === null
            ? (string) $value
            : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return match ($driver) {
            'enc', 'encrypted' => 'enc:'.Crypt::encryptString($serialized),
            'base64' => 'base64:'.base64_encode($serialized),
            'raw_base64' => base64_encode($serialized),
            default => $serialized,
        };
    }

    /**
     * Prepare a full payload array (value, crc, hash, type) for direct query insertion or update.
     *
     * @return array{value: string, type: string, crc: int, hash: string, description: string|null, updated_at: Carbon}
     */
    public static function payload(
        mixed $value,
        string $driver = 'base64',
        ?string $type = null,
        ?string $description = null,
    ): array {
        $detectedType = $type ?? match (true) {
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_bool($value) => 'bool',
            is_array($value) => 'json',
            default => 'string',
        };

        $encodedValue = self::encode($value, $driver);
        $crc = ConfigBuilder::computeCrc($encodedValue);
        $hash = self::hash($encodedValue);

        return [
            'value' => $encodedValue,
            'type' => $detectedType,
            'crc' => $crc,
            'hash' => $hash,
            'description' => $description,
            'updated_at' => now(),
        ];
    }

    /**
     * Cast string representation into appropriate PHP type.
     */
    public function castValue(mixed $value, ?string $type = null): mixed
    {
        if (! is_string($value) || $type === null) {
            return $value;
        }

        return match (strtolower($type)) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'decimal' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }
}
