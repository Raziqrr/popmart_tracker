<?php

namespace App\Console\Commands;

use App\Services\Jules\JulesQueueManager;
use App\Services\Jules\JulesQuotaTracker;
use App\Services\Jules\JulesTier;
use Illuminate\Console\Command;

class JulesQuotaCommand extends Command
{
    protected $signature = 'jules:quota
        {--json : Format metrics output as JSON}
        {--tier= : Test capacity against a specific tier (free, pro, ultra)}';

    protected $description = 'Check Google Jules quota limits, rolling 24-hour capacity, and usage maximization advice';

    public function handle(JulesQueueManager $queueManager): int
    {
        $tierOverride = $this->option('tier') ? JulesTier::fromString($this->option('tier')) : null;
        $quotaTracker = $tierOverride !== null
            ? new JulesQuotaTracker($tierOverride)
            : $queueManager->quotaTracker;

        $tasks = array_values($queueManager->loadTasks());
        $metrics = $quotaTracker->getMetrics($tasks);
        $recommendations = $quotaTracker->getMaximizationRecommendations($tasks);

        if ($this->option('json')) {
            $this->line(json_encode([
                'metrics' => $metrics,
                'recommendations' => $recommendations,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('======================================================');
        $this->info("   Google Jules Quota Monitor - {$metrics['tier_label']}");
        $this->info('======================================================');
        $this->newLine();

        $this->table(
            ['Metric', 'Current', 'Limit', 'Utilization', 'Status'],
            [
                [
                    'Concurrency (Active Tasks)',
                    (string) $metrics['concurrency_active'],
                    (string) $metrics['concurrency_limit'],
                    $metrics['concurrency_utilization_pct'].'%',
                    $this->formatBar($metrics['concurrency_active'], $metrics['concurrency_limit']),
                ],
                [
                    'Rolling 24h Tasks',
                    (string) $metrics['daily_used'],
                    (string) $metrics['daily_limit'],
                    $metrics['daily_utilization_pct'].'%',
                    $this->formatBar($metrics['daily_used'], $metrics['daily_limit']),
                ],
                [
                    'Rolling 30d Tasks (Monthly)',
                    (string) $metrics['monthly_used'],
                    (string) $metrics['monthly_limit'],
                    $metrics['monthly_utilization_pct'].'%',
                    $this->formatBar($metrics['monthly_used'], $metrics['monthly_limit']),
                ],
                [
                    'Queued Pending Tasks',
                    (string) $metrics['queued_count'],
                    '-',
                    '-',
                    $metrics['queued_count'] > 0 ? "<fg=yellow>{$metrics['queued_count']} awaiting dispatch</>" : '<fg=green>Queue clear</>',
                ],
                [
                    'Available Concurrency Slots',
                    (string) $metrics['concurrency_available'],
                    (string) $metrics['concurrency_limit'],
                    '-',
                    $metrics['concurrency_available'] > 0 ? "<fg=green>{$metrics['concurrency_available']} ready</>" : '<fg=red>Saturated</>',
                ],
            ]
        );

        $this->newLine();
        $this->line('Dispatch Readiness: '.($metrics['can_dispatch'] ? '<fg=green>READY TO DISPATCH</>' : '<fg=yellow>WAITING FOR CAPACITY</>'));
        if (! empty($metrics['next_slot_available_at'])) {
            $this->line("Next Slot Available At: <fg=cyan>{$metrics['next_slot_available_at']}</>");
        }

        $this->newLine();
        $this->comment('Quota Maximization Insights:');
        if (empty($recommendations)) {
            $this->line('  - All systems optimal. Quota utilization is well balanced.');
        } else {
            foreach ($recommendations as $rec) {
                $this->line("  * {$rec}");
            }
        }
        $this->newLine();

        return self::SUCCESS;
    }

    private function formatBar(int $current, int $max, int $width = 15): string
    {
        if ($max <= 0) {
            return '[----------------]';
        }

        $ratio = min(1.0, max(0.0, $current / $max));
        $filled = (int) round($ratio * $width);
        $empty = $width - $filled;

        $bar = str_repeat('=', $filled).str_repeat('-', $empty);

        if ($ratio >= 1.0) {
            return "<fg=red>[{$bar}] FULL</>";
        }

        if ($ratio >= 0.8) {
            return "<fg=yellow>[{$bar}] HIGH</>";
        }

        return "<fg=green>[{$bar}] OK</>";
    }
}
