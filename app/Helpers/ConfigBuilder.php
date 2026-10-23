<?php

namespace App\Helpers;

use App\Exceptions\ConfigCrcMismatchException;
use App\Exceptions\ConfigHashMismatchException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Handles retrieving application configs from the database with CRC32 integrity verification
 * and SHA-256 hash checks via ConfigDecoder.
 *
 * Configs are stored in the `configs` table. Setting is performed via direct queries,
 * while ConfigBuilder handles getting, verifying, decoding, and casting configuration values.
 */
class ConfigBuilder
{
    /** @var array<string, mixed> In-memory request cache */
    protected array $cache = [];

    protected ConfigDecoder $decoder;

    public function __construct(
        protected string $table = 'configs',
        protected bool $fallbackToLaravelConfig = true,
        ?ConfigDecoder $decoder = null,
    ) {
        $this->decoder = $decoder ?? app(ConfigDecoder::class);
    }

    /**
     * Compute the unsigned 32-bit CRC checksum of a raw value.
     */
    public static function computeCrc(mixed $value): int
    {
        $payload = is_scalar($value) || $value === null
            ? (string) $value
            : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return (int) sprintf('%u', crc32($payload));
    }

    /**
     * Compute the SHA-256 hash of a string value.
     */
    public static function computeHash(string $value): string
    {
        return ConfigDecoder::hash($value);
    }

    /**
     * Direct query builder instance for setting or updating configs directly.
     */
    public static function directQuery(string $table = 'configs'): Builder
    {
        return DB::table($table);
    }

    /**
     * Set a config directly via query with its calculated CRC and SHA-256 hash.
     */
    public static function directSet(
        string $key,
        mixed $value,
        ?string $type = null,
        ?string $description = null,
        string $driver = 'plain',
        string $table = 'configs',
    ): int {
        $detectedType = $type ?? match (true) {
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_bool($value) => 'bool',
            is_array($value) => 'json',
            default => 'string',
        };

        $stringValue = $driver === 'plain'
            ? match ($detectedType) {
                'json', 'array' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'bool', 'boolean' => $value ? '1' : '0',
                default => (string) $value,
            }
        : ConfigDecoder::encode($value, $driver);

        $crc = self::computeCrc($stringValue);
        $hash = ConfigDecoder::hash($stringValue);

        DB::table($table)->updateOrInsert(
            ['key' => $key],
            [
                'value' => $stringValue,
                'type' => $detectedType,
                'crc' => $crc,
                'hash' => $hash,
                'description' => $description,
                'updated_at' => now(),
            ]
        );

        return $crc;
    }

    /**
     * Retrieve a configuration value from the database with CRC integrity and SHA-256 hash check,
     * decoded via ConfigDecoder.
     *
     * @param  string  $key  The config key (e.g., 'apis.popmart.calls_per_minute')
     * @param  mixed  $default  Fallback value if key is not found
     * @param  int|null  $expectedCrc  Optional expected CRC checksum to assert against
     * @param  bool  $fresh  Bypass memory cache and read directly from DB
     * @param  string|null  $expectedHash  Optional expected SHA-256 hash to assert against
     * @return mixed The decoded configuration value
     *
     * @throws ConfigCrcMismatchException If database CRC or expected CRC does not match computed CRC
     * @throws ConfigHashMismatchException If database hash or expected hash does not match computed SHA-256
     */
    public function get(
        string $key,
        mixed $default = null,
        ?int $expectedCrc = null,
        bool $fresh = false,
        ?string $expectedHash = null,
    ): mixed {
        if (! $fresh && array_key_exists($key, $this->cache)) {
            if ($expectedCrc !== null) {
                $rawRow = $this->raw($key, fresh: false);
                $actualCrc = $rawRow ? (int) $rawRow->crc : self::computeCrc($rawRow?->value ?? '');
                if ($actualCrc !== $expectedCrc) {
                    throw ConfigCrcMismatchException::forExpected($key, $expectedCrc, $actualCrc);
                }
            }

            if ($expectedHash !== null) {
                $rawRow = $this->raw($key, fresh: false);
                $actualHash = $rawRow ? ($rawRow->hash ?? ConfigDecoder::hash($rawRow->value)) : '';
                if (! hash_equals(strtolower($expectedHash), strtolower($actualHash))) {
                    throw new ConfigHashMismatchException($key, $expectedHash, $actualHash);
                }
            }

            return $this->cache[$key];
        }

        $row = $this->query()->where('key', $key)->first();

        if ($row === null) {
            if ($default !== null) {
                return $default;
            }

            return $this->fallbackToLaravelConfig ? config($key, $default) : $default;
        }

        $computedCrc = self::computeCrc($row->value);

        if ($row->crc !== null && (int) $row->crc !== $computedCrc) {
            throw ConfigCrcMismatchException::forRow($key, (int) $row->crc, $computedCrc);
        }

        if ($expectedCrc !== null && $expectedCrc !== $computedCrc) {
            throw ConfigCrcMismatchException::forExpected($key, $expectedCrc, $computedCrc);
        }

        if ($expectedHash !== null) {
            $computedHash = ConfigDecoder::hash($row->value);
            if (! hash_equals(strtolower($expectedHash), strtolower($computedHash))) {
                throw new ConfigHashMismatchException($key, $expectedHash, $computedHash);
            }
        }

        $decodedValue = $this->decoder->decode($row->value, $row->hash ?? null, $row->type, $key);
        $this->cache[$key] = $decodedValue;

        return $decodedValue;
    }

    /**
     * Shortcut to fetch a fresh config value directly from the database.
     */
    public function fresh(
        string $key,
        mixed $default = null,
        ?int $expectedCrc = null,
        ?string $expectedHash = null,
    ): mixed {
        return $this->get($key, $default, $expectedCrc, fresh: true, expectedHash: $expectedHash);
    }

    /**
     * Check if a config key exists in the database.
     */
    public function has(string $key, bool $fresh = false): bool
    {
        if (! $fresh && array_key_exists($key, $this->cache)) {
            return true;
        }

        return $this->query()->where('key', $key)->exists();
    }

    /**
     * Get the raw database row for a key after verifying its CRC and SHA-256 hash.
     */
    public function raw(string $key, bool $fresh = false): ?object
    {
        $row = $this->query()->where('key', $key)->first();

        if ($row === null) {
            return null;
        }

        $computedCrc = self::computeCrc($row->value);

        if ($row->crc !== null && (int) $row->crc !== $computedCrc) {
            throw ConfigCrcMismatchException::forRow($key, (int) $row->crc, $computedCrc);
        }

        if ($row->hash !== null && ! $this->decoder->verifyHash($row->value, $row->hash)) {
            throw new ConfigHashMismatchException($key, $row->hash, ConfigDecoder::hash($row->value));
        }

        return $row;
    }

    /**
     * Get the CRC of a config from the database.
     */
    public function crc(string $key, bool $fresh = false): ?int
    {
        $row = $this->raw($key, $fresh);

        return $row ? (int) ($row->crc ?? self::computeCrc($row->value)) : null;
    }

    /**
     * Get the SHA-256 hash of a config from the database.
     */
    public function hash(string $key, bool $fresh = false): ?string
    {
        $row = $this->raw($key, $fresh);

        return $row ? ($row->hash ?? ConfigDecoder::hash($row->value)) : null;
    }

    /**
     * Verify that a config exists and passes both CRC and SHA-256 hash checks.
     */
    public function verify(string $key, ?int $expectedCrc = null, ?string $expectedHash = null): bool
    {
        try {
            $row = $this->query()->where('key', $key)->first();
            if ($row === null) {
                return false;
            }

            $computedCrc = self::computeCrc($row->value);
            if ($row->crc !== null && (int) $row->crc !== $computedCrc) {
                return false;
            }

            if ($expectedCrc !== null && $expectedCrc !== $computedCrc) {
                return false;
            }

            if ($row->hash !== null && ! $this->decoder->verifyHash($row->value, $row->hash)) {
                return false;
            }

            if ($expectedHash !== null && ! $this->decoder->verifyHash($row->value, $expectedHash)) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get all configs from the database as a key-value associative array.
     * Every row is validated with CRC and SHA-256 checks and decoded via ConfigDecoder.
     *
     * @return array<string, mixed>
     *
     * @throws ConfigCrcMismatchException If any config fails its CRC check
     * @throws ConfigHashMismatchException If any config fails its hash check
     */
    public function all(bool $fresh = false): array
    {
        $rows = $this->query()->get();
        $results = [];

        foreach ($rows as $row) {
            $computedCrc = self::computeCrc($row->value);

            if ($row->crc !== null && (int) $row->crc !== $computedCrc) {
                throw ConfigCrcMismatchException::forRow($row->key, (int) $row->crc, $computedCrc);
            }

            $results[$row->key] = $this->decoder->decode($row->value, $row->hash ?? null, $row->type, $row->key);
        }

        if ($fresh) {
            $this->cache = $results;
        } else {
            $this->cache = array_merge($this->cache, $results);
        }

        return $results;
    }

    /**
     * Clear the in-memory cache.
     */
    public function clearCache(): static
    {
        $this->cache = [];

        return $this;
    }

    /**
     * Get the decoder instance.
     */
    public function getDecoder(): ConfigDecoder
    {
        return $this->decoder;
    }

    protected function query(): Builder
    {
        return DB::table($this->table);
    }
}
