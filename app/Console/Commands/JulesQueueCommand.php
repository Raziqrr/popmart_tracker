<?php

namespace App\Console\Commands;

use App\Services\Jules\JulesQueueManager;
use App\Services\Jules\JulesTask;
use Illuminate\Console\Command;

class JulesQueueCommand extends Command
{
    protected $signature = 'jules:queue
        {action=list : Action to perform: list, add, dispatch, complete, fail, cleanup, clear}
        {--prompt= : Task instructions for Jules (for action=add)}
        {--issue= : GitHub issue number}
        {--branch=main : Target git branch}
        {--priority=normal : Priority level: urgent, high, normal, low}
        {--task-id= : Target Task ID (for complete, fail)}
        {--error= : Failure error reason (for fail)}
        {--batch=0 : Max batch size to dispatch (0 = dispatch up to available slots)}
        {--json : Format output as JSON}';

    protected $description = 'Manage Google Jules task queue, dispatch queued jobs, and track task state';

    public function handle(JulesQueueManager $queueManager): int
    {
        $action = strtolower(trim($this->argument('action')));

        return match ($action) {
            'list' => $this->handleList($queueManager),
            'add' => $this->handleAdd($queueManager),
            'dispatch' => $this->handleDispatch($queueManager),
            'complete' => $this->handleComplete($queueManager),
            'fail' => $this->handleFail($queueManager),
            'cleanup' => $this->handleCleanup($queueManager),
            'clear' => $this->handleClear($queueManager),
            default => $this->invalidAction($action),
        };
    }

    private function handleList(JulesQueueManager $queueManager): int
    {
        $metrics = $queueManager->getMetrics();
        $active = $queueManager->activeTasks();
        $queued = $queueManager->queuedTasks();
        $completed = $queueManager->completedTasks(10);

        if ($this->option('json')) {
            $this->line(json_encode([
                'metrics' => $metrics,
                'active' => array_map(fn (JulesTask $t) => $t->toArray(), $active),
                'queued' => array_map(fn (JulesTask $t) => $t->toArray(), $queued),
                'recent_completed' => array_map(fn (JulesTask $t) => $t->toArray(), $completed),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("=== Google Jules Task Queue ({$metrics['tier_label']}) ===");
        $this->line("Concurrency: {$metrics['concurrency_active']}/{$metrics['concurrency_limit']} active ({$metrics['concurrency_available']} available slots)");
        $this->line("Daily Quota: {$metrics['daily_used']}/{$metrics['daily_limit']} used ({$metrics['daily_remaining']} remaining in rolling 24h)");
        $this->newLine();

        if (! empty($active)) {
            $this->comment('Active / Dispatched Tasks:');
            $this->table(
                ['ID', 'Priority', 'Issue', 'Branch', 'Dispatched At', 'Prompt Preview'],
                array_map(fn (JulesTask $t) => [
                    $t->id,
                    strtoupper($t->priority),
                    $t->issueNumber ? "#{$t->issueNumber}" : '-',
                    $t->branch,
                    $t->dispatchedAt?->diffForHumans() ?? '-',
                    mb_substr($t->prompt, 0, 45).'...',
                ], $active)
            );
        } else {
            $this->line('No tasks currently active.');
        }

        if (! empty($queued)) {
            $this->newLine();
            $this->comment('Queued Tasks (ordered by priority & age):');
            $this->table(
                ['ID', 'Priority', 'Issue', 'Branch', 'Queued At', 'Prompt Preview'],
                array_map(fn (JulesTask $t) => [
                    $t->id,
                    strtoupper($t->priority),
                    $t->issueNumber ? "#{$t->issueNumber}" : '-',
                    $t->branch,
                    $t->createdAt?->diffForHumans() ?? '-',
                    mb_substr($t->prompt, 0, 45).'...',
                ], $queued)
            );
        } else {
            $this->line('Queue is currently empty.');
        }

        if (! empty($completed)) {
            $this->newLine();
            $this->comment('Recently Finished Tasks:');
            $this->table(
                ['ID', 'Status', 'Issue', 'Finished At', 'Error / Result'],
                array_map(fn (JulesTask $t) => [
                    $t->id,
                    $t->status,
                    $t->issueNumber ? "#{$t->issueNumber}" : '-',
                    $t->completedAt?->diffForHumans() ?? '-',
                    $t->error ? mb_substr($t->error, 0, 35) : 'OK',
                ], $completed)
            );
        }

        return self::SUCCESS;
    }

    private function handleAdd(JulesQueueManager $queueManager): int
    {
        $prompt = $this->option('prompt');
        if (empty($prompt)) {
            $this->error('The --prompt option is required when adding a task.');

            return self::FAILURE;
        }

        $task = $queueManager->enqueue($prompt, [
            'issue_number' => $this->option('issue'),
            'branch' => $this->option('branch') ?: 'main',
            'priority' => $this->option('priority') ?: JulesTask::PRIORITY_NORMAL,
        ]);

        if ($this->option('json')) {
            $this->line(json_encode($task->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Enqueued Jules task: {$task->id} [Priority: {$task->priority}, Branch: {$task->branch}]");
        }

        return self::SUCCESS;
    }

    private function handleDispatch(JulesQueueManager $queueManager): int
    {
        $batch = (int) $this->option('batch');
        $dispatched = $queueManager->dispatchNextBatch($batch);

        if ($this->option('json')) {
            $this->line(json_encode(array_map(fn (JulesTask $t) => $t->toArray(), $dispatched), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (empty($dispatched)) {
            $metrics = $queueManager->getMetrics();
            if ($metrics['queued_count'] === 0) {
                $this->line('No queued tasks available for dispatch.');
            } else {
                $this->warn("Cannot dispatch: Concurrency full or daily limit reached ({$metrics['concurrency_active']}/{$metrics['concurrency_limit']} active, {$metrics['daily_remaining']} daily remaining).");
            }

            return self::SUCCESS;
        }

        $this->info('Dispatched '.count($dispatched).' task(s) to Google Jules:');
        foreach ($dispatched as $task) {
            $this->line("  - [{$task->id}] ({$task->priority}): {$task->branch} -> ".mb_substr($task->prompt, 0, 50).'...');
        }

        return self::SUCCESS;
    }

    private function handleComplete(JulesQueueManager $queueManager): int
    {
        $taskId = $this->option('task-id');
        if (empty($taskId)) {
            $this->error('Please specify --task-id to mark as complete.');

            return self::FAILURE;
        }

        if ($queueManager->markCompleted($taskId)) {
            $this->info("Task {$taskId} marked as completed.");

            return self::SUCCESS;
        }

        $this->error("Task {$taskId} not found.");

        return self::FAILURE;
    }

    private function handleFail(JulesQueueManager $queueManager): int
    {
        $taskId = $this->option('task-id');
        if (empty($taskId)) {
            $this->error('Please specify --task-id to mark as failed.');

            return self::FAILURE;
        }

        $error = $this->option('error') ?: 'Unknown failure';
        if ($queueManager->markFailed($taskId, $error)) {
            $this->warn("Task {$taskId} marked as failed: {$error}");

            return self::SUCCESS;
        }

        $this->error("Task {$taskId} not found.");

        return self::FAILURE;
    }

    private function handleCleanup(JulesQueueManager $queueManager): int
    {
        $cleaned = $queueManager->cleanupStaleTasks();
        $this->info("Cleaned up {$cleaned} timed-out task(s).");

        return self::SUCCESS;
    }

    private function handleClear(JulesQueueManager $queueManager): int
    {
        $queueManager->clear(includeQueued: false);
        $this->info('Cleared completed and failed tasks from state history.');

        return self::SUCCESS;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Unknown action: {$action}. Valid actions: list, add, dispatch, complete, fail, cleanup, clear.");

        return self::FAILURE;
    }
}
