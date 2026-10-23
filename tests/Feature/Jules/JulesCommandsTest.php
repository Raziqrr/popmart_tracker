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
        ->expectsOutputToContain('Rolling 30d Tasks (Monthly)')
        ->assertSuccessful();
});

test('jules:quota command supports JSON output format', function () {
    Artisan::call('jules:quota', ['--json' => true]);
    $output = json_decode(Artisan::output(), true);

    expect($output)->toHaveKeys(['metrics', 'recommendations'])
        ->and($output['metrics'])->toHaveKeys(['tier', 'daily_limit', 'monthly_limit', 'concurrency_limit'])
        ->and($output['metrics']['daily_limit'])->toBe(15)
        ->and($output['metrics']['monthly_limit'])->toBe(450);
});

test('jules:quota command respects tier flag override', function () {
    Artisan::call('jules:quota', ['--tier' => 'ultra', '--json' => true]);
    $output = json_decode(Artisan::output(), true);

    expect($output['metrics']['tier'])->toBe('ultra')
        ->and($output['metrics']['daily_limit'])->toBe(300)
        ->and($output['metrics']['monthly_limit'])->toBe(9000)
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

test('jules:queue complete, fail, and clear work with positional arguments', function () {
    // 1. Add two tasks
    Artisan::call('jules:queue', ['action' => 'add', '--prompt' => 'Task A']);
    Artisan::call('jules:queue', ['action' => 'add', '--prompt' => 'Task B']);
    Artisan::call('jules:queue', ['action' => 'dispatch']);

    $manager = new \App\Services\Jules\JulesQueueManager(storagePath: $this->testStoragePath);
    $active = $manager->activeTasks();
    expect($active)->toHaveCount(2);

    $taskA = $active[0]->id;
    $taskB = $active[1]->id;

    // Complete task A via positional argument
    $this->artisan("jules:queue complete {$taskA}")
        ->expectsOutputToContain("Task {$taskA} marked as completed.")
        ->assertSuccessful();

    // Fail task B via positional argument
    $this->artisan("jules:queue fail {$taskB} --error=\"Build failed\"")
        ->expectsOutputToContain("Task {$taskB} marked as failed: Build failed")
        ->assertSuccessful();

    // Clear all tasks
    $this->artisan('jules:queue clear --all')
        ->expectsOutputToContain('Cleared all tasks from queue state.')
        ->assertSuccessful();

    $reloaded = new \App\Services\Jules\JulesQueueManager(storagePath: $this->testStoragePath);
    expect($reloaded->loadTasks())->toBeEmpty();
});
