#!/usr/bin/env php
<?php

use App\Services\Jules\JulesQueueManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/**
 * Standalone Google Jules Queue & Quota Maximization CLI
 *
 * Can be run in CI workflows, git hooks, crons, or local environments:
 *   php scripts/jules-queue.php status
 *   php scripts/jules-queue.php evaluate --prompt="Fix adapter" --issue=10
 *   php scripts/jules-queue.php add --prompt="Add feature" --priority=high
 *   php scripts/jules-queue.php dispatch
 *   php scripts/jules-queue.php mark-dispatched --task-id=jules-task-123
 *   php scripts/jules-queue.php complete --task-id=jules-task-123
 */

declare(strict_types=1);

$autoloadPath = __DIR__.'/../vendor/autoload.php';
$bootstrapPath = __DIR__.'/../bootstrap/app.php';

if (file_exists($autoloadPath) && file_exists($bootstrapPath)) {
    require_once $autoloadPath;
    /** @var Application $app */
    $app = require_once $bootstrapPath;
    $app->make(Kernel::class)->bootstrap();

    /** @var JulesQueueManager $manager */
    $manager = $app->make(JulesQueueManager::class);

    runWithLaravel($manager, $argv);
    exit(0);
}

// Fallback when Laravel autoloader is not booted
runFallback($argv);

/**
 * Execution handler using Laravel Services
 */
function runWithLaravel(JulesQueueManager $manager, array $argv): void
{
    $command = $argv[1] ?? 'status';
    $options = parseArgs(array_slice($argv, 2));

    switch ($command) {
        case 'status':
            $metrics = $manager->getMetrics();
            $recommendations = $manager->quotaTracker->getMaximizationRecommendations(array_values($manager->loadTasks()));
            echo json_encode([
                'metrics' => $metrics,
                'active' => array_map(fn ($t) => $t->toArray(), $manager->activeTasks()),
                'queued' => array_map(fn ($t) => $t->toArray(), $manager->queuedTasks()),
                'recommendations' => $recommendations,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
            break;

        case 'evaluate':
            $prompt = $options['prompt'] ?? null;
            $result = $manager->evaluateAndDispatch($prompt, [
                'issue_number' => $options['issue'] ?? null,
                'branch' => $options['branch'] ?? 'main',
                'priority' => $options['priority'] ?? 'normal',
                'is_schedule' => $options['is-schedule'] ?? false,
            ]);
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
            break;

        case 'add':
            $prompt = $options['prompt'] ?? '';
            if (empty($prompt)) {
                fwrite(STDERR, "Error: --prompt is required\n");
                exit(1);
            }
            $task = $manager->enqueue($prompt, [
                'issue_number' => $options['issue'] ?? null,
                'branch' => $options['branch'] ?? 'main',
                'priority' => $options['priority'] ?? 'normal',
            ]);
            echo json_encode($task->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
            break;

        case 'dispatch':
            $batch = isset($options['batch']) ? (int) $options['batch'] : 0;
            $dispatched = $manager->dispatchNextBatch($batch);
            echo json_encode(array_map(fn ($t) => $t->toArray(), $dispatched), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
            break;

        case 'mark-dispatched':
            $taskId = $options['task-id'] ?? ($argv[2] ?? '');
            if (empty($taskId)) {
                fwrite(STDERR, "Error: --task-id is required\n");
                exit(1);
            }
            $success = $manager->markDispatched($taskId);
            echo json_encode(['success' => $success, 'task_id' => $taskId]).PHP_EOL;
            break;

        case 'complete':
            $taskId = $options['task-id'] ?? ($argv[2] ?? '');
            if (empty($taskId)) {
                fwrite(STDERR, "Error: --task-id is required\n");
                exit(1);
            }
            $success = $manager->markCompleted($taskId);
            echo json_encode(['success' => $success, 'task_id' => $taskId]).PHP_EOL;
            break;

        case 'fail':
            $taskId = $options['task-id'] ?? ($argv[2] ?? '');
            $error = $options['error'] ?? 'Unknown error';
            if (empty($taskId)) {
                fwrite(STDERR, "Error: --task-id is required\n");
                exit(1);
            }
            $success = $manager->markFailed($taskId, $error);
            echo json_encode(['success' => $success, 'task_id' => $taskId, 'error' => $error]).PHP_EOL;
            break;

        case 'cleanup':
            $cleaned = $manager->cleanupStaleTasks();
            echo json_encode(['cleaned' => $cleaned]).PHP_EOL;
            break;

        default:
            fwrite(STDERR, "Unknown command: {$command}\n");
            exit(1);
    }
}

/**
 * Fallback runner for standalone execution without composer autoloader
 */
function runFallback(array $argv): void
{
    $command = $argv[1] ?? 'status';
    $options = parseArgs(array_slice($argv, 2));
    $storageFile = __DIR__.'/../storage/app/jules/queue.json';

    $data = file_exists($storageFile) ? json_decode((string) file_get_contents($storageFile), true) : ['tasks' => []];
    $tasks = $data['tasks'] ?? [];

    $dailyLimit = 15;
    $concurrencyLimit = 3;
    $now = time();
    $cutoff = $now - (24 * 3600);

    $dailyUsed = 0;
    $activeCount = 0;
    $queuedCount = 0;

    foreach ($tasks as $t) {
        $status = $t['status'] ?? 'queued';
        $dispatchedAt = isset($t['dispatched_at']) ? strtotime($t['dispatched_at']) : 0;

        if ($dispatchedAt >= $cutoff) {
            $dailyUsed++;
        }
        if (in_array($status, ['dispatched', 'running'], true)) {
            $activeCount++;
        }
        if ($status === 'queued') {
            $queuedCount++;
        }
    }

    $canDispatch = ($dailyLimit - $dailyUsed > 0) && ($concurrencyLimit - $activeCount > 0);

    if ($command === 'evaluate') {
        $prompt = $options['prompt'] ?? '';
        $taskId = 'jules-fallback-'.bin2hex(random_bytes(4));

        if (! empty($prompt)) {
            $tasks[] = [
                'id' => $taskId,
                'prompt' => $prompt,
                'issue_number' => $options['issue'] ?? null,
                'branch' => $options['branch'] ?? 'main',
                'priority' => $options['priority'] ?? 'normal',
                'status' => $canDispatch ? 'dispatched' : 'queued',
                'created_at' => date(DATE_ISO8601),
                'dispatched_at' => $canDispatch ? date(DATE_ISO8601) : null,
            ];
            @mkdir(dirname($storageFile), 0755, true);
            file_put_contents($storageFile, json_encode(['tasks' => $tasks], JSON_PRETTY_PRINT));
        }

        echo json_encode([
            'can_dispatch' => $canDispatch,
            'task_id' => $taskId,
            'dispatch_prompt' => $prompt,
            'reason' => $canDispatch ? 'Dispatched' : 'Queued (Capacity reached)',
            'metrics' => [
                'daily_used' => $dailyUsed,
                'daily_limit' => $dailyLimit,
                'concurrency_active' => $activeCount,
                'concurrency_limit' => $concurrencyLimit,
                'queued_count' => $queuedCount,
            ],
        ], JSON_PRETTY_PRINT).PHP_EOL;

        return;
    }

    echo json_encode([
        'tier' => 'free',
        'can_dispatch' => $canDispatch,
        'daily_used' => $dailyUsed,
        'daily_limit' => $dailyLimit,
        'concurrency_active' => $activeCount,
        'concurrency_limit' => $concurrencyLimit,
        'queued_count' => $queuedCount,
    ], JSON_PRETTY_PRINT).PHP_EOL;
}

/**
 * Parse CLI flags (--flag=value or --flag value)
 *
 * @param  array<string>  $args
 * @return array<string, string>
 */
function parseArgs(array $args): array
{
    $options = [];
    $total = count($args);

    for ($i = 0; $i < $total; $i++) {
        $arg = $args[$i];
        if (str_starts_with($arg, '--')) {
            $key = substr($arg, 2);
            if (str_contains($key, '=')) {
                [$k, $v] = explode('=', $key, 2);
                $options[$k] = $v;
            } elseif ($i + 1 < $total && ! str_starts_with($args[$i + 1], '--')) {
                $options[$key] = $args[++$i];
            } else {
                $options[$key] = 'true';
            }
        }
    }

    return $options;
}
