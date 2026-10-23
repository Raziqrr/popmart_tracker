<?php

use App\Exceptions\ConfigCrcMismatchException;
use App\Exceptions\ConfigHashMismatchException;
use App\Helpers\ConfigBuilder;
use App\Helpers\ConfigDecoder;
use App\Helpers\EndpointBuilder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

it('reads a config from the database with a valid crc and sha-256 hash', function () {
    $builder = new ConfigBuilder;

    // Direct database query insertion with computed CRC and SHA-256
    $val = 'https://custom-api.popmart.com';
    $crc = ConfigBuilder::computeCrc($val);
    $hash = ConfigDecoder::hash($val);

    DB::table('configs')->insert([
        'key' => 'apis.popmart.base_url',
        'value' => $val,
        'type' => 'string',
        'crc' => $crc,
        'hash' => $hash,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($builder->get('apis.popmart.base_url'))->toBe('https://custom-api.popmart.com')
        ->and($builder->crc('apis.popmart.base_url'))->toBe($crc)
        ->and($builder->hash('apis.popmart.base_url'))->toBe($hash)
        ->and($builder->verify('apis.popmart.base_url'))->toBeTrue();
});

it('decodes base64-encoded config values via ConfigDecoder', function () {
    $builder = new ConfigBuilder;

    // Store a base64 encoded secret via direct query
    $plainSecret = 'my-super-secret-api-token';
    $encoded = 'base64:'.base64_encode($plainSecret);
    $crc = ConfigBuilder::computeCrc($encoded);
    $hash = ConfigDecoder::hash($encoded);

    DB::table('configs')->insert([
        'key' => 'popmart.api_secret',
        'value' => $encoded,
        'type' => 'string',
        'crc' => $crc,
        'hash' => $hash,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($builder->get('popmart.api_secret'))->toBe($plainSecret);
});

it('decodes encrypted config values via ConfigDecoder', function () {
    $builder = new ConfigBuilder;

    $original = 'confidential-credential-data';
    $encrypted = 'enc:'.Crypt::encryptString($original);
    $crc = ConfigBuilder::computeCrc($encrypted);
    $hash = ConfigDecoder::hash($encrypted);

    DB::table('configs')->insert([
        'key' => 'auth.oauth_secret',
        'value' => $encrypted,
        'type' => 'string',
        'crc' => $crc,
        'hash' => $hash,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($builder->get('auth.oauth_secret'))->toBe($original);
});

it('throws ConfigHashMismatchException when stored hash does not match computed sha-256', function () {
    $builder = new ConfigBuilder;

    $val = 'legitimate-unaltered-value';
    $crc = ConfigBuilder::computeCrc($val);
    $tamperedHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'; // Not matching

    DB::table('configs')->insert([
        'key' => 'tampered.hash.key',
        'value' => $val,
        'type' => 'string',
        'crc' => $crc,
        'hash' => $tamperedHash,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $builder->get('tampered.hash.key'))
        ->toThrow(ConfigHashMismatchException::class, 'Hash verification failed for config [tampered.hash.key]');
});

it('throws ConfigHashMismatchException when caller expected hash does not match', function () {
    $builder = new ConfigBuilder;

    $val = 'sample-value';
    ConfigBuilder::directSet('sample.key', $val, 'string');

    expect(fn () => $builder->get('sample.key', expectedHash: '0000000000000000000000000000000000000000000000000000000000000000'))
        ->toThrow(ConfigHashMismatchException::class);

    $validHash = ConfigDecoder::hash($val);
    expect($builder->get('sample.key', expectedHash: $validHash))->toBe($val);
});

it('casts types correctly from decoded database values', function () {
    $builder = new ConfigBuilder;

    // Base64-encoded integer: '100' -> MTAw
    ConfigBuilder::directSet('app.max_items', 100, 'int', driver: 'base64');
    expect($builder->get('app.max_items'))->toBe(100);

    // Base64-encoded float: '3.14'
    ConfigBuilder::directSet('app.rate', 3.14, 'float', driver: 'base64');
    expect($builder->get('app.rate'))->toBe(3.14);

    // Base64-encoded boolean: '1'
    ConfigBuilder::directSet('app.feature_enabled', true, 'bool', driver: 'base64');
    expect($builder->get('app.feature_enabled'))->toBeTrue();

    // Base64-encoded JSON array: ['MY', 'SG', 'TH']
    ConfigBuilder::directSet('app.allowed_regions', ['MY', 'SG', 'TH'], 'json', driver: 'base64');
    expect($builder->get('app.allowed_regions'))->toBe(['MY', 'SG', 'TH']);
});

it('throws ConfigCrcMismatchException when database stored crc does not match computed crc', function () {
    $builder = new ConfigBuilder;

    // Insert corrupt record where stored CRC does not match value
    DB::table('configs')->insert([
        'key' => 'corrupted.key',
        'value' => 'legitimate_value',
        'type' => 'string',
        'crc' => 12345678, // Wrong CRC
        'hash' => ConfigDecoder::hash('legitimate_value'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $builder->get('corrupted.key'))
        ->toThrow(ConfigCrcMismatchException::class, 'Database CRC mismatch for config [corrupted.key]');
});

it('throws ConfigCrcMismatchException when expected crc does not match computed crc', function () {
    $builder = new ConfigBuilder;

    ConfigBuilder::directSet('secure.token', 'my-secret-token', 'string');

    // Passing wrong expected CRC
    expect(fn () => $builder->get('secure.token', expectedCrc: 99999999))
        ->toThrow(ConfigCrcMismatchException::class, 'Expected CRC mismatch for config [secure.token]');

    // Passing correct expected CRC succeeds
    $correctCrc = ConfigBuilder::computeCrc('my-secret-token');
    expect($builder->get('secure.token', expectedCrc: $correctCrc))->toBe('my-secret-token');
});

it('falls back to default or Laravel config when key does not exist in db', function () {
    $builder = new ConfigBuilder;

    // Exists in config/apis.php: apis.popmart.calls_per_minute is 60
    expect($builder->get('apis.popmart.calls_per_minute'))->toBe(60);

    // Non-existent key with fallback default
    expect($builder->get('non.existent.key', 'fallback_value'))->toBe('fallback_value');
});

it('handles setting through direct query and reads the updated value', function () {
    $builder = new ConfigBuilder;

    // Direct SQL insert/update query
    $val = '120';
    $crc = ConfigBuilder::computeCrc($val);
    $hash = ConfigDecoder::hash($val);
    DB::table('configs')->insert([
        'key' => 'apis.popmart.calls_per_minute',
        'value' => $val,
        'type' => 'int',
        'crc' => $crc,
        'hash' => $hash,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($builder->get('apis.popmart.calls_per_minute'))->toBe(120);

    // Direct query update with base64 encoded value
    $newVal = '180';
    $newEncoded = 'base64:'.base64_encode($newVal);
    DB::table('configs')->where('key', 'apis.popmart.calls_per_minute')->update([
        'value' => $newEncoded,
        'crc' => ConfigBuilder::computeCrc($newEncoded),
        'hash' => ConfigDecoder::hash($newEncoded),
        'updated_at' => now(),
    ]);

    expect($builder->fresh('apis.popmart.calls_per_minute'))->toBe(180);
});

it('retrieves and validates all configs with all()', function () {
    $builder = new ConfigBuilder;

    ConfigBuilder::directSet('cfg.one', 'alpha', driver: 'base64');
    ConfigBuilder::directSet('cfg.two', 42, 'int', driver: 'base64');

    $all = $builder->all();
    expect($all)->toHaveKey('cfg.one', 'alpha')
        ->and($all)->toHaveKey('cfg.two', 42);
});

it('integrates with EndpointBuilder to dynamically resolve base URLs', function () {
    // Override base_url in database directly with base64 encoded value
    ConfigBuilder::directSet('apis.popmart.base_url', 'https://override-api.popmart.com', driver: 'base64');

    $endpointBuilder = new EndpointBuilder('popmart');
    $url = $endpointBuilder->build(['rpc', 'draw', 'test']);

    expect($url)->toBe('https://override-api.popmart.com/rpc/draw/test');
});
