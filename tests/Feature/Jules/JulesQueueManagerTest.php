<?php

use App\Services\Jules\JulesQueueManager;
use App\Services\Jules\JulesQuotaTracker;
use App\Services\Jules\JulesTask;
use App\Services\Jules\JulesTier;
use Illuminate\Support\Carbon;

function julesTestStoragePath(): string
{
    return storage_path('framework/testing/jules_test_queue.json');
}

beforeEach(function () {
    if (file_exists(julesTestStoragePath())) {
        @unlink(julesTestStoragePath());
    }
});

afterEach(function () {
    if (file_exists(julesTestStoragePath())) {
        @unlink(julesTestStoragePath());
    }
});

test('queue manager enqueues tasks and persists them to json', function () {
    $manager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free),
        storagePath: julesTestStoragePath()
    );

    $task = $manager->enqueue('Implement Pop Mart adapter check', [
        'issue_number' => 42,
        'branch' => 'feature/adapter',
        'priority' => JulesTask::PRIORITY_HIGH,
    ]);

    expect($task->id)->toStartWith('jules-task-')
        ->and($task->status)->toBe(JulesTask::STATUS_QUEUED)
        ->and($task->priority)->toBe(JulesTask::PRIORITY_HIGH)
        ->and($task->issueNumber)->toBe(42)
        ->and(file_exists(julesTestStoragePath()))->toBeTrue();

    $reloaded = $manager->loadTasks();
    expect($reloaded)->toHaveKey($task->id)
        ->and($reloaded[$task->id]->prompt)->toBe('Implement Pop Mart adapter check');
});

test('queue manager respects priority ordering (urgent > high > normal > low)', function () {
    $manager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free),
        storagePath: julesTestStoragePath()
    );

    $low = $manager->enqueue('Low task', ['priority' => JulesTask::PRIORITY_LOW]);
    $urgent = $manager->enqueue('Urgent task', ['priority' => JulesTask::PRIORITY_URGENT]);
    $normal = $manager->enqueue('Normal task', ['priority' => JulesTask::PRIORITY_NORMAL]);
    $high = $manager->enqueue('High task', ['priority' => JulesTask::PRIORITY_HIGH]);

    $queued = $manager->queuedTasks();

    expect($queued[0]->id)->toBe($urgent->id)
        ->and($queued[1]->id)->toBe($high->id)
        ->and($queued[2]->id)->toBe($normal->id)
        ->and($queued[3]->id)->toBe($low->id);
});

test('evaluateAndDispatch dispatches immediately when quota is available', function () {
    $manager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free),
        storagePath: julesTestStoragePath()
    );

    $result = $manager->evaluateAndDispatch('Investigate box prediction accuracy', [
        'priority' => JulesTask::PRIORITY_URGENT,
    ]);

    expect($result['can_dispatch'])->toBeTrue()
        ->and($result['task_id'])->not()->toBeNull()
        ->and($result['dispatch_prompt'])->toBe('Investigate box prediction accuracy');

    $active = $manager->activeTasks();
    expect($active)->toHaveCount(1)
        ->and($active[0]->status)->toBe(JulesTask::STATUS_DISPATCHED);
});

test('evaluateAndDispatch queues task and rejects dispatch when concurrency is full', function () {
    $manager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free, dailyLimitOverride: 15, concurrencyLimitOverride: 1),
        storagePath: julesTestStoragePath()
    );

    // First task takes the only concurrency slot
    $first = $manager->evaluateAndDispatch('Task 1');
    expect($first['can_dispatch'])->toBeTrue();

    // Second task must be queued
    $second = $manager->evaluateAndDispatch('Task 2');
    expect($second['can_dispatch'])->toBeFalse()
        ->and($second['task_id'])->not()->toBeNull()
        ->and($second['reason'])->toContain('Concurrency limit reached');

    expect($manager->activeTasks())->toHaveCount(1)
        ->and($manager->queuedTasks())->toHaveCount(1);
});

test('dispatchNextBatch dispatches tasks up to available capacity', function () {
    $manager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free, concurrencyLimitOverride: 2),
        storagePath: julesTestStoragePath()
    );

    $manager->enqueue('Task A', ['priority' => JulesTask::PRIORITY_NORMAL]);
    $manager->enqueue('Task B', ['priority' => JulesTask::PRIORITY_HIGH]);
    $manager->enqueue('Task C', ['priority' => JulesTask::PRIORITY_LOW]);

    $dispatched = $manager->dispatchNextBatch();

    // Max concurrency is 2, so only 2 should be dispatched
    expect($dispatched)->toHaveCount(2)
        ->and($dispatched[0]->prompt)->toBe('Task B') // higher priority
        ->and($dispatched[1]->prompt)->toBe('Task A')
        ->and($manager->queuedTasks())->toHaveCount(1); // Task C remains queued
});

test('markCompleted and markFailed update task status', function () {
    $manager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free),
        storagePath: julesTestStoragePath()
    );

    $t1 = $manager->enqueue('Task 1');
    $t2 = $manager->enqueue('Task 2');

    $manager->markDispatched($t1->id);
    $manager->markCompleted($t1->id, ['pr_url' => 'https://github.com/.../pull/1']);

    $manager->markDispatched($t2->id);
    $manager->markFailed($t2->id, 'CI failed');

    $task1 = $manager->getTask($t1->id);
    $task2 = $manager->getTask($t2->id);

    expect($task1->status)->toBe(JulesTask::STATUS_COMPLETED)
        ->and($task1->completedAt)->not()->toBeNull()
        ->and($task2->status)->toBe(JulesTask::STATUS_FAILED)
        ->and($task2->error)->toBe('CI failed');
});

test('cleanupStaleTasks marks timed out tasks as timed_out to free slots', function () {
    $manager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free),
        storagePath: julesTestStoragePath(),
        taskTimeoutMinutes: 30
    );

    $t = $manager->enqueue('Stale task');
    $manager->markDispatched($t->id);

    // Simulate task running 45 minutes ago
    $tasks = $manager->loadTasks();
    $tasks[$t->id]->dispatchedAt = Carbon::now()->subMinutes(45);
    $manager->saveTasks($tasks);

    $cleaned = $manager->cleanupStaleTasks(timeoutMinutes: 30);

    expect($cleaned)->toBe(1);
    $updated = $manager->getTask($t->id);
    expect($updated->status)->toBe(JulesTask::STATUS_TIMED_OUT)
        ->and($manager->activeTasks())->toHaveCount(0);
});

test('evaluateAndDispatch with autoDispatch false leaves task queued until markDispatched', function () {
    $manager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free),
        storagePath: julesTestStoragePath()
    );

    $result = $manager->evaluateAndDispatch('Test reserve only', autoDispatch: false);

    expect($result['can_dispatch'])->toBeTrue()
        ->and($result['task_id'])->not()->toBeNull()
        ->and($manager->activeTasks())->toHaveCount(0)
        ->and($manager->queuedTasks())->toHaveCount(1);

    $manager->markDispatched($result['task_id']);
    expect($manager->activeTasks())->toHaveCount(1)
        ->and($manager->queuedTasks())->toHaveCount(0);
});

test('loadTasks automatically reconciles stale tasks on load', function () {
    $manager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free),
        storagePath: julesTestStoragePath(),
        taskTimeoutMinutes: 30
    );

    $t = $manager->enqueue('Task to stall');
    $manager->markDispatched($t->id);

    // Manipulate file directly to set dispatched_at to 60 minutes ago
    $tasks = $manager->loadTasks();
    $tasks[$t->id]->dispatchedAt = Carbon::now()->subMinutes(60);
    $manager->saveTasks($tasks);

    // Instantiate a fresh manager to test loadTasks auto-reconciliation
    $freshManager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free),
        storagePath: julesTestStoragePath(),
        taskTimeoutMinutes: 30
    );

    $loaded = $freshManager->loadTasks();
    expect($loaded[$t->id]->status)->toBe(JulesTask::STATUS_TIMED_OUT)
        ->and($freshManager->activeTasks())->toHaveCount(0);
});

test('clear retains queued tasks by default and clears all when includeQueued is true', function () {
    $manager = new JulesQueueManager(
        quotaTracker: new JulesQuotaTracker(JulesTier::Free),
        storagePath: julesTestStoragePath()
    );

    $q = $manager->enqueue('Queued task');
    $d = $manager->enqueue('Completed task');
    $manager->markDispatched($d->id);
    $manager->markCompleted($d->id);

    // Default clear: keeps queued
    $manager->clear(includeQueued: false);
    expect($manager->loadTasks())->toHaveKey($q->id)
        ->and($manager->loadTasks())->not()->toHaveKey($d->id);

    // Clear all: wipes everything
    $manager->clear(includeQueued: true);
    expect($manager->loadTasks())->toBeEmpty();
});
