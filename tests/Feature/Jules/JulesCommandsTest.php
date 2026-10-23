<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->testStoragePath = storage_path('framework/testing/jules_commands_test.json');
    config()->set('jules.storage_path', $this->testStoragePath);
    if (file_exists($this->testStoragePath)) {
        @unlink($this->testStoragePath);
    }
});

afterEach(function () {
    if (isset($this->testStoragePath) && file_exists($this->testStoragePath)) {
        @unlink($this->testStoragePath);
    }
});

test('jules:quota command runs and outputs metrics table', function () {
    $this->artisan('jules:quota')
        ->expectsOutputToContain('Google Jules Quota Monitor')
        ->expectsOutputToContain('Concurrency')
        ->expectsOutputToContain('Rolling 24h Tasks')
        ->assertSuccessful();
});

test('jules:quota command supports JSON output format', function () {
    Artisan::call('jules:quota', ['--json' => true]);
    $output = json_decode(Artisan::output(), true);

    expect($output)->toHaveKeys(['metrics', 'recommendations'])
        ->and($output['metrics'])->toHaveKeys(['tier', 'daily_limit', 'concurrency_limit'])
        ->and($output['metrics']['daily_limit'])->toBe(15);
});

test('jules:quota command respects tier flag override', function () {
    Artisan::call('jules:quota', ['--tier' => 'ultra', '--json' => true]);
    $output = json_decode(Artisan::output(), true);

    expect($output['metrics']['tier'])->toBe('ultra')
        ->and($output['metrics']['daily_limit'])->toBe(300)
        ->and($output['metrics']['concurrency_limit'])->toBe(60);
});

test('jules:queue add, list, dispatch and cleanup work via artisan', function () {
    // 1. Add task
    $this->artisan('jules:queue add --prompt="Verify LuckyPointsAdapter" --priority=high --issue=50')
        ->expectsOutputToContain('Enqueued Jules task:')
        ->assertSuccessful();

    // 2. List queue
    $this->artisan('jules:queue list')
        ->expectsOutputToContain('Queued Tasks')
        ->assertSuccessful();

    // 3. Dispatch queue
    $this->artisan('jules:queue dispatch')
        ->expectsOutputToContain('Dispatched 1 task(s) to Google Jules:')
        ->assertSuccessful();

    // 4. Cleanup
    $this->artisan('jules:queue cleanup')
        ->expectsOutputToContain('Cleaned up 0 timed-out task(s).')
        ->assertSuccessful();
});
