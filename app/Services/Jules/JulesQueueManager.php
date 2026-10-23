<?php

namespace App\Services\Jules;

use Illuminate\Support\Carbon;
use RuntimeException;

class JulesQueueManager
{
    public readonly JulesQuotaTracker $quotaTracker;

    public readonly string $storagePath;

    public function __construct(
        ?JulesQuotaTracker $quotaTracker = null,
        ?string $storagePath = null,
        public int $taskTimeoutMinutes = 60,
    ) {
        $tier = JulesTier::fromString(function_exists('config') ? config('jules.tier', 'free') : 'free');
        $dailyLimit = function_exists('config') ? config('jules.daily_limit') : null;
        $monthlyLimit = function_exists('config') ? config('jules.monthly_limit') : null;
        $concurrencyLimit = function_exists('config') ? config('jules.concurrency_limit') : null;

        $this->quotaTracker = $quotaTracker ?? new JulesQuotaTracker($tier, $dailyLimit, $concurrencyLimit, $monthlyLimit);
        $defaultStorage = function_exists('storage_path') ? storage_path('app/jules/queue.json') : dirname(__DIR__, 2).'/storage/app/jules/queue.json';
        $this->storagePath = $storagePath ?? (function_exists('config') ? config('jules.storage_path') : null) ?? $defaultStorage;
        $this->taskTimeoutMinutes = $taskTimeoutMinutes ?: (int) (function_exists('config') ? config('jules.task_timeout_minutes', 60) : 60);
    }

    /**
     * @return array<string, JulesTask>
     */
    public function loadTasks(bool $autoCleanup = true): array
    {
        if (! file_exists($this->storagePath)) {
            return [];
        }

        $content = @file_get_contents($this->storagePath);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded) || ! isset($decoded['tasks']) || ! is_array($decoded['tasks'])) {
            return [];
        }

        $tasks = [];
        foreach ($decoded['tasks'] as $item) {
            if (is_array($item) && isset($item['id'])) {
                $task = JulesTask::fromArray($item);
                $tasks[$task->id] = $task;
            }
        }

        if ($autoCleanup && $this->cleanupStaleTasksInternal($tasks) > 0) {
            $this->saveTasks($tasks);
        }

        return $tasks;
    }

    /**
     * @param  array<string, JulesTask>  $tasks
     */
    public function saveTasks(array $tasks): void
    {
        $dir = dirname($this->storagePath);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new RuntimeException("Unable to create Jules queue directory: {$dir}");
            }
        }

        $payload = [
            'updated_at' => Carbon::now()->toIso8601String(),
            'tasks' => array_values(array_map(fn (JulesTask $task) => $task->toArray(), $tasks)),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tmpFile = $this->storagePath.'.tmp.'.uniqid('', true);

        if (@file_put_contents($tmpFile, $json, LOCK_EX) === false) {
            throw new RuntimeException("Failed to write temporary queue file: {$tmpFile}");
        }

        if (! @rename($tmpFile, $this->storagePath)) {
            if (PHP_OS_FAMILY === 'Windows' && file_exists($this->storagePath)) {
                @unlink($this->storagePath);
                if (! @rename($tmpFile, $this->storagePath)) {
                    @unlink($tmpFile);
                    throw new RuntimeException("Failed to atomically replace queue file: {$this->storagePath}");
                }
            } else {
                @unlink($tmpFile);
                throw new RuntimeException("Failed to atomically replace queue file: {$this->storagePath}");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function enqueue(string $prompt, array $options = []): JulesTask
    {
        $tasks = $this->loadTasks();
        $this->cleanupStaleTasksInternal($tasks);

        $id = 'jules-task-'.substr(bin2hex(random_bytes(6)), 0, 10);
        $task = new JulesTask(
            id: $id,
            prompt: trim($prompt),
            issueNumber: isset($options['issue_number']) && $options['issue_number'] !== '' ? (int) $options['issue_number'] : null,
            branch: (string) ($options['branch'] ?? 'main'),
            priority: (string) ($options['priority'] ?? JulesTask::PRIORITY_NORMAL),
            status: JulesTask::STATUS_QUEUED,
            createdAt: Carbon::now(),
            metadata: is_array($options['metadata'] ?? null) ? $options['metadata'] : [],
        );

        $tasks[$task->id] = $task;
        $this->saveTasks($tasks);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     can_dispatch: bool,
     *     task_id: ?string,
     *     dispatch_prompt: ?string,
     *     branch: ?string,
     *     issue_number: ?int,
     *     priority: ?string,
     *     reason: string,
     *     metrics: array
     * }
     */
    public function evaluateAndDispatch(?string $newPrompt = null, array $options = [], bool $autoDispatch = true): array
    {
        $tasks = $this->loadTasks();
        $this->cleanupStaleTasksInternal($tasks);

        $now = Carbon::now();

        // If a new task was submitted
        if (! empty($newPrompt)) {
            $task = $this->enqueue($newPrompt, $options);
            $tasks = $this->loadTasks(); // reload with newly added task
        } else {
            $task = null;
        }

        // Determine if we can dispatch
        $canDispatch = $this->quotaTracker->canDispatch(array_values($tasks), $now);
        $metrics = $this->quotaTracker->getMetrics(array_values($tasks), $now);

        if (! $canDispatch) {
            if ($metrics['daily_remaining'] <= 0) {
                $reason = "Daily quota exhausted ({$metrics['daily_used']}/{$metrics['daily_limit']} used in rolling 24h). Next slot opens at {$metrics['next_slot_available_at']}.";
            } elseif ($metrics['monthly_remaining'] <= 0) {
                $reason = "Monthly quota exhausted ({$metrics['monthly_used']}/{$metrics['monthly_limit']} used in rolling 30d). Next slot opens at {$metrics['next_slot_available_at']}.";
            } else {
                $reason = "Concurrency limit reached ({$metrics['concurrency_active']}/{$metrics['concurrency_limit']} tasks active).";
            }

            return [
                'can_dispatch' => false,
                'task_id' => $task?->id,
                'dispatch_prompt' => null,
                'branch' => $task?->branch ?? ($options['branch'] ?? 'main'),
                'issue_number' => $task?->issueNumber ?? (isset($options['issue_number']) && $options['issue_number'] !== '' ? (int) $options['issue_number'] : null),
                'priority' => $task?->priority ?? ($options['priority'] ?? JulesTask::PRIORITY_NORMAL),
                'reason' => $reason,
                'metrics' => $metrics,
            ];
        }

        // If we can dispatch, find the best queued candidate (either the newly submitted task, or highest priority in queue)
        $targetTask = null;
        if ($task !== null && $task->isQueued()) {
            $targetTask = $task;
        } else {
            $queued = $this->getSortedQueuedTasks($tasks);
            if (! empty($queued)) {
                $targetTask = $queued[0];
            }
        }

        if ($targetTask === null) {
            return [
                'can_dispatch' => false,
                'task_id' => null,
                'dispatch_prompt' => null,
                'branch' => null,
                'issue_number' => null,
                'priority' => null,
                'reason' => 'No pending tasks queued.',
                'metrics' => $metrics,
            ];
        }

        if ($autoDispatch) {
            $targetTask->status = JulesTask::STATUS_DISPATCHED;
            $targetTask->dispatchedAt = $now;
            $tasks[$targetTask->id] = $targetTask;
            $this->saveTasks($tasks);
        }

        $updatedMetrics = $this->quotaTracker->getMetrics(array_values($tasks), $now);

        return [
            'can_dispatch' => true,
            'task_id' => $targetTask->id,
            'dispatch_prompt' => $targetTask->prompt,
            'branch' => $targetTask->branch,
            'issue_number' => $targetTask->issueNumber,
            'priority' => $targetTask->priority,
            'reason' => "Task dispatched under {$metrics['tier_label']}.",
            'metrics' => $updatedMetrics,
        ];
    }

    /**
     * @return array<JulesTask>
     */
    public function dispatchNextBatch(int $limit = 0): array
    {
        $tasks = $this->loadTasks();
        $this->cleanupStaleTasksInternal($tasks);

        $now = Carbon::now();
        $availableSlots = $this->quotaTracker->getAvailableConcurrencySlots(array_values($tasks));
        $remainingDaily = $this->quotaTracker->getRemainingDailyCapacity(array_values($tasks), $now);

        $capacity = min($availableSlots, $remainingDaily);
        if ($limit > 0) {
            $capacity = min($capacity, $limit);
        }

        if ($capacity <= 0) {
            return [];
        }

        $queued = $this->getSortedQueuedTasks($tasks);
        $dispatched = [];

        foreach ($queued as $task) {
            if (count($dispatched) >= $capacity) {
                break;
            }

            $task->status = JulesTask::STATUS_DISPATCHED;
            $task->dispatchedAt = Carbon::now();
            $tasks[$task->id] = $task;
            $dispatched[] = $task;
        }

        if (! empty($dispatched)) {
            $this->saveTasks($tasks);
        }

        return $dispatched;
    }

    public function markDispatched(string $taskId): bool
    {
        $tasks = $this->loadTasks();
        if (! isset($tasks[$taskId])) {
            return false;
        }

        $tasks[$taskId]->status = JulesTask::STATUS_DISPATCHED;
        $tasks[$taskId]->dispatchedAt = Carbon::now();
        $this->saveTasks($tasks);

        return true;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markCompleted(string $taskId, array $result = []): bool
    {
        $tasks = $this->loadTasks();
        if (! isset($tasks[$taskId])) {
            return false;
        }

        $tasks[$taskId]->status = JulesTask::STATUS_COMPLETED;
        $tasks[$taskId]->completedAt = Carbon::now();
        if (! empty($result)) {
            $tasks[$taskId]->metadata = array_merge($tasks[$taskId]->metadata, ['result' => $result]);
        }

        $this->saveTasks($tasks);

        return true;
    }

    public function markFailed(string $taskId, string $error): bool
    {
        $tasks = $this->loadTasks();
        if (! isset($tasks[$taskId])) {
            return false;
        }

        $tasks[$taskId]->status = JulesTask::STATUS_FAILED;
        $tasks[$taskId]->completedAt = Carbon::now();
        $tasks[$taskId]->error = $error;
        $this->saveTasks($tasks);

        return true;
    }

    public function cleanupStaleTasks(?int $timeoutMinutes = null): int
    {
        $tasks = $this->loadTasks(autoCleanup: false);
        $cleaned = $this->cleanupStaleTasksInternal($tasks, $timeoutMinutes);

        if ($cleaned > 0) {
            $this->saveTasks($tasks);
        }

        return $cleaned;
    }

    /**
     * @param  array<string, JulesTask>  $tasks
     */
    private function cleanupStaleTasksInternal(array &$tasks, ?int $timeoutMinutes = null): int
    {
        $timeout = $timeoutMinutes ?? $this->taskTimeoutMinutes;
        $now = Carbon::now();
        $cleaned = 0;

        foreach ($tasks as $task) {
            if ($task->isStale($timeout, $now)) {
                $task->status = JulesTask::STATUS_TIMED_OUT;
                $task->completedAt = $now;
                $task->error = "Task timed out after {$timeout} minutes without status update.";
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * @param  array<string, JulesTask>  $tasks
     * @return array<JulesTask>
     */
    private function getSortedQueuedTasks(array $tasks): array
    {
        $queued = array_values(array_filter($tasks, fn (JulesTask $t) => $t->isQueued()));

        usort($queued, function (JulesTask $a, JulesTask $b) {
            $weightDiff = $b->priorityWeight() <=> $a->priorityWeight();
            if ($weightDiff !== 0) {
                return $weightDiff;
            }

            return ($a->createdAt?->timestamp ?? 0) <=> ($b->createdAt?->timestamp ?? 0);
        });

        return $queued;
    }

    public function clear(bool $includeQueued = false): void
    {
        if ($includeQueued) {
            $this->saveTasks([]);

            return;
        }

        $tasks = $this->loadTasks();
        $remaining = [];
        foreach ($tasks as $id => $task) {
            if ($task->isActive() || $task->isQueued()) {
                $remaining[$id] = $task;
            }
        }

        $this->saveTasks($remaining);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $tasks = array_values($this->loadTasks());

        return $this->quotaTracker->getMetrics($tasks);
    }

    /**
     * @return array<JulesTask>
     */
    public function allTasks(): array
    {
        return array_values($this->loadTasks());
    }

    /**
     * @return array<JulesTask>
     */
    public function queuedTasks(): array
    {
        return $this->getSortedQueuedTasks($this->loadTasks());
    }

    /**
     * @return array<JulesTask>
     */
    public function activeTasks(): array
    {
        return array_values(array_filter($this->loadTasks(), fn (JulesTask $t) => $t->isActive()));
    }

    /**
     * @return array<JulesTask>
     */
    public function completedTasks(int $limit = 20): array
    {
        $finished = array_values(array_filter($this->loadTasks(), fn (JulesTask $t) => $t->isFinished()));
        usort($finished, fn (JulesTask $a, JulesTask $b) => ($b->completedAt?->timestamp ?? 0) <=> ($a->completedAt?->timestamp ?? 0));

        return array_slice($finished, 0, $limit);
    }

    public function getTask(string $id): ?JulesTask
    {
        $tasks = $this->loadTasks();

        return $tasks[$id] ?? null;
    }
}
