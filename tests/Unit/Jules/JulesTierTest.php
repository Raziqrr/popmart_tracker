<?php

use App\Services\Jules\JulesTier;

test('jules tier returns standard daily and concurrency limits', function () {
    expect(JulesTier::Free->dailyLimit())->toBe(15)
        ->and(JulesTier::Free->concurrencyLimit())->toBe(3)
        ->and(JulesTier::Pro->dailyLimit())->toBe(100)
        ->and(JulesTier::Pro->concurrencyLimit())->toBe(15)
        ->and(JulesTier::Ultra->dailyLimit())->toBe(300)
        ->and(JulesTier::Ultra->concurrencyLimit())->toBe(60);
});

test('jules tier respects explicit limit overrides', function () {
    expect(JulesTier::Free->dailyLimit(25))->toBe(25)
        ->and(JulesTier::Free->concurrencyLimit(5))->toBe(5)
        ->and(JulesTier::Pro->dailyLimit(150))->toBe(150);
});

test('jules tier can be resolved from strings case-insensitively with fallback', function () {
    expect(JulesTier::fromString('PRO'))->toBe(JulesTier::Pro)
        ->and(JulesTier::fromString('ultra'))->toBe(JulesTier::Ultra)
        ->and(JulesTier::fromString('free'))->toBe(JulesTier::Free)
        ->and(JulesTier::fromString('unknown_tier'))->toBe(JulesTier::Free)
        ->and(JulesTier::fromString(null))->toBe(JulesTier::Free);
});
