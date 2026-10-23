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
            $autoDispatch = ! isset($options['reserve-only']) && ! isset($options['no-dispatch']);
            $result = $manager->evaluateAndDispatch($prompt, [
                'issue_number' => $options['issue'] ?? null,
                'branch' => $options['branch'] ?? 'main',
                'priority' => $options['priority'] ?? 'normal',
                'is_schedule' => $options['is-schedule'] ?? false,
            ], $autoDispatch);
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
    $tasks = is_array($data) && isset($data['tasks']) && is_array($data['tasks']) ? $data['tasks'] : [];

    $dailyLimit = isset($_ENV['JULES_DAILY_LIMIT']) ? (int) $_ENV['JULES_DAILY_LIMIT'] : 15;
    $monthlyLimit = isset($_ENV['JULES_MONTHLY_LIMIT']) ? (int) $_ENV['JULES_MONTHLY_LIMIT'] : 450;
    $concurrencyLimit = isset($_ENV['JULES_CONCURRENCY_LIMIT']) ? (int) $_ENV['JULES_CONCURRENCY_LIMIT'] : 3;
    $timeoutMinutes = isset($_ENV['JULES_TASK_TIMEOUT_MINUTES']) ? (int) $_ENV['JULES_TASK_TIMEOUT_MINUTES'] : 60;

    $now = time();
    $cutoff24h = $now - (24 * 3600);
    $cutoff30d = $now - (30 * 24 * 3600);

    // 1. Auto cleanup stale tasks
    $cleaned = 0;
    foreach ($tasks as &$t) {
        $status = $t['status'] ?? 'queued';
        $dispatchedAt = isset($t['dispatched_at']) ? strtotime($t['dispatched_at']) : 0;
        if (in_array($status, ['dispatched', 'running'], true) && $dispatchedAt > 0 && ($now - $dispatchedAt) >= ($timeoutMinutes * 60)) {
            $t['status'] = 'timed_out';
            $t['completed_at'] = date(DATE_ATOM);
            $t['error'] = "Task timed out after {$timeoutMinutes} minutes without status update.";
            $cleaned++;
        }
    }
    unset($t);

    $dailyUsed = 0;
    $monthlyUsed = 0;
    $activeCount = 0;
    $queuedCount = 0;
    $completedCount = 0;

    foreach ($tasks as $t) {
        $status = $t['status'] ?? 'queued';
        $dispatchedAt = isset($t['dispatched_at']) ? strtotime($t['dispatched_at']) : 0;

        if ($dispatchedAt >= $cutoff24h) {
            $dailyUsed++;
        }
        if ($dispatchedAt >= $cutoff30d) {
            $monthlyUsed++;
        }
        if (in_array($status, ['dispatched', 'running'], true)) {
            $activeCount++;
        }
        if ($status === 'queued') {
            $queuedCount++;
        }
        if ($status === 'completed') {
            $completedCount++;
        }
    }

    $canDispatch = ($dailyLimit - $dailyUsed > 0) && ($monthlyLimit - $monthlyUsed > 0) && ($concurrencyLimit - $activeCount > 0);

    $saveStorage = function (array $tasksToSave) use ($storageFile): void {
        $dir = dirname($storageFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $payload = [
            'updated_at' => date(DATE_ATOM),
            'tasks' => array_values($tasksToSave),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $tmpFile = $storageFile.'.tmp.'.bin2hex(random_bytes(4));
        file_put_contents($tmpFile, $json);
        if (! @rename($tmpFile, $storageFile)) {
            if (file_exists($storageFile)) {
                @unlink($storageFile);
                @rename($tmpFile, $storageFile);
            }
        }
    };

    if ($cleaned > 0) {
        $saveStorage($tasks);
    }

    $priorityWeight = fn (string $p) => match (strtolower($p)) {
        'urgent' => 4,
        'high' => 3,
        'normal' => 2,
        'low' => 1,
        default => 2,
    };

    switch ($command) {
        case 'status':
            echo json_encode([
                'tier' => 'free',
                'daily_limit' => $dailyLimit,
                'daily_used' => $dailyUsed,
                'daily_remaining' => max(0, $dailyLimit - $dailyUsed),
                'monthly_limit' => $monthlyLimit,
                'monthly_used' => $monthlyUsed,
                'monthly_remaining' => max(0, $monthlyLimit - $monthlyUsed),
                'concurrency_limit' => $concurrencyLimit,
                'concurrency_active' => $activeCount,
                'concurrency_available' => max(0, $concurrencyLimit - $activeCount),
                'can_dispatch' => $canDispatch,
                'queued_count' => $queuedCount,
                'completed_count' => $completedCount,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
            break;

        case 'evaluate':
            $prompt = $options['prompt'] ?? '';
            $targetTask = null;

            if (! empty($prompt)) {
                $newTask = [
                    'id' => 'jules-fallback-'.substr(bin2hex(random_bytes(6)), 0, 10),
                    'prompt' => trim($prompt),
                    'issue_number' => isset($options['issue']) && $options['issue'] !== '' ? (int) $options['issue'] : null,
                    'branch' => $options['branch'] ?? 'main',
                    'priority' => $options['priority'] ?? 'normal',
                    'status' => 'queued',
                    'created_at' => date(DATE_ATOM),
                    'dispatched_at' => null,
                    'completed_at' => null,
                    'error' => null,
                    'metadata' => [],
                ];
                $tasks[] = $newTask;
                $queuedCount++;
                $targetTask = $newTask;
            }

            if (! $canDispatch) {
                if ($dailyLimit - $dailyUsed <= 0) {
                    $reason = "Daily quota exhausted ({$dailyUsed}/{$dailyLimit} used in rolling 24h).";
                } elseif ($monthlyLimit - $monthlyUsed <= 0) {
                    $reason = "Monthly quota exhausted ({$monthlyUsed}/{$monthlyLimit} used in rolling 30d).";
                } else {
                    $reason = "Concurrency limit reached ({$activeCount}/{$concurrencyLimit} tasks active).";
                }

                $saveStorage($tasks);
                echo json_encode([
                    'can_dispatch' => false,
                    'task_id' => $targetTask['id'] ?? null,
                    'dispatch_prompt' => null,
                    'branch' => $targetTask['branch'] ?? ($options['branch'] ?? 'main'),
                    'issue_number' => $targetTask['issue_number'] ?? null,
                    'priority' => $targetTask['priority'] ?? 'normal',
                    'reason' => $reason,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

                return;
            }

            // Find best queued candidate
            if ($targetTask === null) {
                $queued = array_values(array_filter($tasks, fn ($t) => ($t['status'] ?? '') === 'queued'));
                usort($queued, function ($a, $b) use ($priorityWeight) {
                    $wDiff = $priorityWeight($b['priority'] ?? 'normal') <=> $priorityWeight($a['priority'] ?? 'normal');
                    if ($wDiff !== 0) {
                        return $wDiff;
                    }

                    return strtotime($a['created_at'] ?? 'now') <=> strtotime($b['created_at'] ?? 'now');
                });
                if (! empty($queued)) {
                    $targetTask = $queued[0];
                }
            }

            if ($targetTask === null) {
                $saveStorage($tasks);
                echo json_encode([
                    'can_dispatch' => false,
                    'task_id' => null,
                    'dispatch_prompt' => null,
                    'branch' => null,
                    'issue_number' => null,
                    'priority' => null,
                    'reason' => 'No pending tasks queued.',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

                return;
            }

            $autoDispatch = ! isset($options['reserve-only']) && ! isset($options['no-dispatch']);
            if ($autoDispatch) {
                foreach ($tasks as &$t) {
                    if ($t['id'] === $targetTask['id']) {
                        $t['status'] = 'dispatched';
                        $t['dispatched_at'] = date(DATE_ATOM);
                        break;
                    }
                }
                unset($t);
            }

            $saveStorage($tasks);

            echo json_encode([
                'can_dispatch' => true,
                'task_id' => $targetTask['id'],
                'dispatch_prompt' => $targetTask['prompt'],
                'branch' => $targetTask['branch'] ?? 'main',
                'issue_number' => $targetTask['issue_number'] ?? null,
                'priority' => $targetTask['priority'] ?? 'normal',
                'reason' => 'Dispatched under Free Tier',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
            break;

        case 'add':
            $prompt = $options['prompt'] ?? '';
            if (empty($prompt)) {
                fwrite(STDERR, "Error: --prompt is required\n");
                exit(1);
            }
            $task = [
                'id' => 'jules-fallback-'.substr(bin2hex(random_bytes(6)), 0, 10),
                'prompt' => trim($prompt),
                'issue_number' => isset($options['issue']) && $options['issue'] !== '' ? (int) $options['issue'] : null,
                'branch' => $options['branch'] ?? 'main',
                'priority' => $options['priority'] ?? 'normal',
                'status' => 'queued',
                'created_at' => date(DATE_ATOM),
                'dispatched_at' => null,
                'completed_at' => null,
                'error' => null,
                'metadata' => [],
            ];
            $tasks[] = $task;
            $saveStorage($tasks);
            echo json_encode($task, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
            break;

        case 'mark-dispatched':
            $taskId = $options['task-id'] ?? ($argv[2] ?? '');
            $found = false;
            foreach ($tasks as &$t) {
                if ($t['id'] === $taskId) {
                    $t['status'] = 'dispatched';
                    $t['dispatched_at'] = date(DATE_ATOM);
                    $found = true;
                    break;
                }
            }
            unset($t);
            if ($found) {
                $saveStorage($tasks);
            }
            echo json_encode(['success' => $found, 'task_id' => $taskId]).PHP_EOL;
            break;

        case 'complete':
            $taskId = $options['task-id'] ?? ($argv[2] ?? '');
            $found = false;
            foreach ($tasks as &$t) {
                if ($t['id'] === $taskId) {
                    $t['status'] = 'completed';
                    $t['completed_at'] = date(DATE_ATOM);
                    $found = true;
                    break;
                }
            }
            unset($t);
            if ($found) {
                $saveStorage($tasks);
            }
            echo json_encode(['success' => $found, 'task_id' => $taskId]).PHP_EOL;
            break;

        case 'fail':
            $taskId = $options['task-id'] ?? ($argv[2] ?? '');
            $error = $options['error'] ?? 'Unknown error';
            $found = false;
            foreach ($tasks as &$t) {
                if ($t['id'] === $taskId) {
                    $t['status'] = 'failed';
                    $t['completed_at'] = date(DATE_ATOM);
                    $t['error'] = $error;
                    $found = true;
                    break;
                }
            }
            unset($t);
            if ($found) {
                $saveStorage($tasks);
            }
            echo json_encode(['success' => $found, 'task_id' => $taskId, 'error' => $error]).PHP_EOL;
            break;

        case 'cleanup':
            echo json_encode(['cleaned' => $cleaned]).PHP_EOL;
            break;

        default:
            fwrite(STDERR, "Unknown command: {$command}\n");
            exit(1);
    }
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
