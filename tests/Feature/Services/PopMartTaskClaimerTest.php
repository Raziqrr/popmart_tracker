<?php

use App\Models\PopmartAccount;
use App\Services\PopMartTaskClaimer;
use App\Services\Api\PopMart\PopMartError;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Locator\Country;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use App\Helpers\ConfigBuilder;

beforeEach(function () {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'apis.popmart.log_calls' => false,
        'apis.popmart.record_fields' => false,
    ]);

    // Mock ConfigBuilder which reaches into the database for base_url
    $mockConfigBuilder = \Mockery::mock(ConfigBuilder::class)->makePartial();
    $mockConfigBuilder->shouldReceive('get')
        ->with('apis.popmart.base_url')
        ->andReturn('https://prod-apac-api.popmart.com');
    $mockConfigBuilder->shouldReceive('get')
        ->with('apis.popmart.calls_per_minute')
        ->andReturn(60);
    $mockConfigBuilder->shouldReceive('get')
        ->with('apis.popmart.background_per_minute')
        ->andReturn(40);
    $mockConfigBuilder->shouldReceive('get')
        ->with('apis.popmart.pause_seconds')
        ->andReturn(120);
    $mockConfigBuilder->shouldReceive('get')
        ->with('apis.popmart.log_calls')
        ->andReturn(false);
    $mockConfigBuilder->shouldReceive('get')
        ->with('apis.popmart.record_fields')
        ->andReturn(false);

    // Bind to container
    $this->app->instance(ConfigBuilder::class, $mockConfigBuilder);
});

it('claims eligible tasks successfully', function () {
    Http::fake([
        '*get-session*' => Http::response(['session' => ['userId' => 'member-1']]),
        '*public_getTasksByActivityAndChannel*' => Http::response(['json' => [
            'tasks' => [['id' => 't1', 'alias' => 'login'], ['id' => 't2', 'alias' => 'share']],
            'userTasks' => [
                ['taskId' => 't1', 'done' => true, 'draw' => false],
                ['taskId' => 't2', 'done' => true, 'draw' => false],
            ],
        ]]),
        '*task/draw*' => Http::response(['json' => ['rewards' => ['points' => 10]]]),
    ]);

    $account = new PopmartAccount(['session_cookie' => 'cookie', 'area' => 'US']);
    $claimer = new PopMartTaskClaimer();
    $results = $claimer->claimEligible($account);

    expect($results)->toHaveCount(2);
    expect($results[0])->toBe(['alias' => 'login', 'status' => 'claimed', 'reward' => ['points' => 10]]);
    expect($results[1])->toBe(['alias' => 'share', 'status' => 'claimed', 'reward' => ['points' => 10]]);
});

it('skips excluded tasks', function () {
    Http::fake([
        '*get-session*' => Http::response(['session' => ['userId' => 'member-1']]),
        '*public_getTasksByActivityAndChannel*' => Http::response(['json' => [
            'tasks' => [['id' => 't1', 'alias' => 'buy']],
            'userTasks' => [
                ['taskId' => 't1', 'done' => true, 'draw' => false],
            ],
        ]]),
        '*task/draw*' => Http::response(['json' => ['rewards' => ['points' => 10]]]),
    ]);

    $account = new PopmartAccount(['session_cookie' => 'cookie', 'area' => 'US']);
    $claimer = new PopMartTaskClaimer();
    $results = $claimer->claimEligible($account);

    expect($results)->toHaveCount(1);
    expect($results[0])->toBe(['alias' => 'buy', 'status' => 'skipped_excluded']);
});

it('skips tasks that are not done or already drawn', function () {
    Http::fake([
        '*get-session*' => Http::response(['session' => ['userId' => 'member-1']]),
        '*public_getTasksByActivityAndChannel*' => Http::response(['json' => [
            'tasks' => [['id' => 't1', 'alias' => 'not_done'], ['id' => 't2', 'alias' => 'already_drawn']],
            'userTasks' => [
                ['taskId' => 't1', 'done' => false, 'draw' => false],
                ['taskId' => 't2', 'done' => true, 'draw' => true],
            ],
        ]]),
        '*task/draw*' => Http::response(['json' => ['rewards' => ['points' => 10]]]),
    ]);

    $account = new PopmartAccount(['session_cookie' => 'cookie', 'area' => 'US']);
    $claimer = new PopMartTaskClaimer();
    $results = $claimer->claimEligible($account);

    expect($results)->toHaveCount(2);
    expect($results[0])->toBe(['alias' => 'not_done', 'status' => 'not_eligible']);
    expect($results[1])->toBe(['alias' => 'already_drawn', 'status' => 'not_eligible']);
});

it('handles individual task claim failures gracefully', function () {
    Http::fake([
        '*get-session*' => Http::response(['session' => ['userId' => 'member-1']]),
        '*public_getTasksByActivityAndChannel*' => Http::response(['json' => [
            'tasks' => [['id' => 't1', 'alias' => 'login'], ['id' => 't2', 'alias' => 'share']],
            'userTasks' => [
                ['taskId' => 't1', 'done' => true, 'draw' => false],
                ['taskId' => 't2', 'done' => true, 'draw' => false],
            ],
        ]]),
        '*task/draw*' => Http::sequence()
            ->push(['error' => ['json' => ['message' => 'Task already claimed']]], 500)
            ->push(['json' => ['rewards' => ['points' => 10]]]),
    ]);

    $account = new PopmartAccount(['session_cookie' => 'cookie', 'area' => 'US']);
    $claimer = new PopMartTaskClaimer();
    $results = $claimer->claimEligible($account);

    expect($results)->toHaveCount(2);
    expect($results[0]['alias'])->toBe('login');
    expect($results[0]['status'])->toBe('failed');
    expect($results[0]['message'])->toContain('Task already claimed');

    expect($results[1])->toBe(['alias' => 'share', 'status' => 'claimed', 'reward' => ['points' => 10]]);
});

it('aborts batch if an exception says it should', function () {
    Http::fake([
        '*get-session*' => Http::response(['session' => ['userId' => 'member-1']]),
        '*public_getTasksByActivityAndChannel*' => Http::response(['json' => [
            'tasks' => [['id' => 't1', 'alias' => 'login'], ['id' => 't2', 'alias' => 'share']],
            'userTasks' => [
                ['taskId' => 't1', 'done' => true, 'draw' => false],
                ['taskId' => 't2', 'done' => true, 'draw' => false],
            ],
        ]]),
        '*task/draw*' => Http::response(null, 401),
    ]);

    $account = new PopmartAccount(['session_cookie' => 'cookie', 'area' => 'US']);
    $claimer = new PopMartTaskClaimer();

    expect(fn() => $claimer->claimEligible($account))->toThrow(PopMartException::class);
});
