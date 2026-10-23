<?php

use App\Services\Jules\JulesQuotaTracker;
use App\Services\Jules\JulesTask;
use App\Services\Jules\JulesTier;
use Illuminate\Support\Carbon;

test('quota tracker counts rolling 24-hour usage accurately', function () {
    $now = Carbon::parse('2026-10-23 12:00:00');
    $tracker = new JulesQuotaTracker(JulesTier::Free);

    $tasks = [
        new JulesTask(id: 't1', prompt: 'test', dispatchedAt: $now->copy()->subHours(2)),
        new JulesTask(id: 't2', prompt: 'test', dispatchedAt: $now->copy()->subHours(23)),
        new JulesTask(id: 't3', prompt: 'test', dispatchedAt: $now->copy()->subHours(25)), // older than 24h
        new JulesTask(id: 't4', prompt: 'test', status: JulesTask::STATUS_QUEUED), // not dispatched
    ];

    expect($tracker->getRolling24hUsage($tasks, $now))->toBe(2)
        ->and($tracker->getRemainingDailyCapacity($tasks, $now))->toBe(13); // 15 - 2 = 13
});

test('quota tracker tracks active concurrency and available slots', function () {
    $tracker = new JulesQuotaTracker(JulesTier::Free); // Concurrency = 3

    $tasks = [
        new JulesTask(id: 't1', prompt: 'test', status: JulesTask::STATUS_DISPATCHED),
        new JulesTask(id: 't2', prompt: 'test', status: JulesTask::STATUS_RUNNING),
        new JulesTask(id: 't3', prompt: 'test', status: JulesTask::STATUS_COMPLETED),
        new JulesTask(id: 't4', prompt: 'test', status: JulesTask::STATUS_QUEUED),
    ];

    expect($tracker->getActiveCount($tasks))->toBe(2)
        ->and($tracker->getAvailableConcurrencySlots($tasks))->toBe(1)
        ->and($tracker->canDispatch($tasks))->toBeTrue();
});

test('quota tracker blocks dispatch when concurrency limit is saturated', function () {
    $tracker = new JulesQuotaTracker(JulesTier::Free); // Concurrency = 3

    $tasks = [
        new JulesTask(id: 't1', prompt: 'test', status: JulesTask::STATUS_DISPATCHED),
        new JulesTask(id: 't2', prompt: 'test', status: JulesTask::STATUS_RUNNING),
        new JulesTask(id: 't3', prompt: 'test', status: JulesTask::STATUS_DISPATCHED),
    ];

    expect($tracker->getActiveCount($tasks))->toBe(3)
        ->and($tracker->getAvailableConcurrencySlots($tasks))->toBe(0)
        ->and($tracker->canDispatch($tasks))->toBeFalse();
});

test('quota tracker blocks dispatch when rolling 24-hour limit is reached', function () {
    $now = Carbon::parse('2026-10-23 12:00:00');
    $tracker = new JulesQuotaTracker(JulesTier::Free); // Daily = 15

    $tasks = [];
    for ($i = 0; $i < 15; $i++) {
        $tasks[] = new JulesTask(
            id: "t{$i}",
            prompt: 'test',
            status: JulesTask::STATUS_COMPLETED,
            dispatchedAt: $now->copy()->subHours(($i % 20) + 1)
        );
    }

    expect($tracker->getRolling24hUsage($tasks, $now))->toBe(15)
        ->and($tracker->getRemainingDailyCapacity($tasks, $now))->toBe(0)
        ->and($tracker->canDispatch($tasks, $now))->toBeFalse();
});

test('quota tracker estimates next available slot correctly when daily limit is exhausted', function () {
    $now = Carbon::parse('2026-10-23 12:00:00');
    $tracker = new JulesQuotaTracker(JulesTier::Free);

    // Oldest task within window was dispatched at 10:00 yesterday (22 hours ago)
    $oldestInWindow = $now->copy()->subHours(22);

    $tasks = [];
    $tasks[] = new JulesTask(id: 'oldest', prompt: 'test', status: JulesTask::STATUS_COMPLETED, dispatchedAt: $oldestInWindow);

    for ($i = 1; $i < 15; $i++) {
        $tasks[] = new JulesTask(
            id: "t{$i}",
            prompt: 'test',
            status: JulesTask::STATUS_COMPLETED,
            dispatchedAt: $now->copy()->subHours(($i % 15) + 1)
        );
    }

    $expectedSlot = $oldestInWindow->copy()->addHours(24);
    $estimate = $tracker->getNextSlotAvailableEstimate($tasks, $now);

    expect($estimate->timestamp)->toBe($expectedSlot->timestamp);
});

test('quota tracker generates actionable maximization recommendations', function () {
    $tracker = new JulesQuotaTracker(JulesTier::Free);

    $tasks = [
        new JulesTask(id: 'q1', prompt: 'test', status: JulesTask::STATUS_QUEUED),
        new JulesTask(id: 'q2', prompt: 'test', status: JulesTask::STATUS_QUEUED),
    ];

    $recommendations = $tracker->getMaximizationRecommendations($tasks);
    expect($recommendations)->not()->toBeEmpty()
        ->and($recommendations[0])->toContain('Available capacity');
});

test('quota tracker tracks rolling 30-day monthly usage and blocks dispatch when saturated', function () {
    $now = Carbon::parse('2026-10-23 12:00:00');
    $tracker = new JulesQuotaTracker(JulesTier::Free, monthlyLimitOverride: 5);

    $tasks = [];
    for ($i = 0; $i < 5; $i++) {
        $tasks[] = new JulesTask(
            id: "m{$i}",
            prompt: 'monthly task',
            status: JulesTask::STATUS_COMPLETED,
            dispatchedAt: $now->copy()->subDays($i + 1)
        );
    }
    // Task older than 30 days
    $tasks[] = new JulesTask(
        id: 'old_month',
        prompt: 'expired month task',
        status: JulesTask::STATUS_COMPLETED,
        dispatchedAt: $now->copy()->subDays(35)
    );

    expect($tracker->getRolling30dUsage($tasks, $now))->toBe(5)
        ->and($tracker->getRemainingMonthlyCapacity($tasks, $now))->toBe(0)
        ->and($tracker->canDispatch($tasks, $now))->toBeFalse();
});

test('quota tracker ensures next available slot estimate is never in the past when concurrency is full', function () {
    $now = Carbon::parse('2026-10-23 12:00:00');
    $tracker = new JulesQuotaTracker(JulesTier::Free); // Concurrency = 3

    // 3 active tasks running for 30 minutes (past typical 15 min estimate)
    $tasks = [
        new JulesTask(id: 'a1', prompt: 't1', status: JulesTask::STATUS_DISPATCHED, dispatchedAt: $now->copy()->subMinutes(30)),
        new JulesTask(id: 'a2', prompt: 't2', status: JulesTask::STATUS_RUNNING, dispatchedAt: $now->copy()->subMinutes(25)),
        new JulesTask(id: 'a3', prompt: 't3', status: JulesTask::STATUS_DISPATCHED, dispatchedAt: $now->copy()->subMinutes(20)),
    ];

    $estimate = $tracker->getNextSlotAvailableEstimate($tasks, $now);
    expect($estimate)->not()->toBeNull()
        ->and($estimate->gt($now))->toBeTrue();
});
