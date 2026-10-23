<?php

namespace App\Services\Jules;

use Illuminate\Support\Carbon;

class JulesQuotaTracker
{
    public function __construct(
        public JulesTier $tier = JulesTier::Free,
        public ?int $dailyLimitOverride = null,
        public ?int $concurrencyLimitOverride = null,
        public ?int $monthlyLimitOverride = null,
    ) {}

    public function getTier(): JulesTier
    {
        return $this->tier;
    }

    public function getDailyLimit(): int
    {
        return $this->tier->dailyLimit($this->dailyLimitOverride);
    }

    public function getMonthlyLimit(): int
    {
        return $this->tier->monthlyLimit($this->monthlyLimitOverride);
    }

    public function getConcurrencyLimit(): int
    {
        return $this->tier->concurrencyLimit($this->concurrencyLimitOverride);
    }

    /**
     * @param  array<JulesTask>  $tasks
     */
    public function getRolling24hUsage(array $tasks, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $cutoff = $now->copy()->subHours(24);

        $count = 0;
        foreach ($tasks as $task) {
            if ($task->dispatchedAt !== null && $task->dispatchedAt->gte($cutoff)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<JulesTask>  $tasks
     */
    public function getRolling30dUsage(array $tasks, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $cutoff = $now->copy()->subDays(30);

        $count = 0;
        foreach ($tasks as $task) {
            if ($task->dispatchedAt !== null && $task->dispatchedAt->gte($cutoff)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<JulesTask>  $tasks
     * @return array<JulesTask>
     */
    public function getActiveTasks(array $tasks): array
    {
        return array_values(array_filter($tasks, fn (JulesTask $task) => $task->isActive()));
    }

    /**
     * @param  array<JulesTask>  $tasks
     */
    public function getActiveCount(array $tasks): int
    {
        return count($this->getActiveTasks($tasks));
    }

    /**
     * @param  array<JulesTask>  $tasks
     */
    public function getRemainingDailyCapacity(array $tasks, ?Carbon $now = null): int
    {
        return max(0, $this->getDailyLimit() - $this->getRolling24hUsage($tasks, $now));
    }

    /**
     * @param  array<JulesTask>  $tasks
     */
    public function getRemainingMonthlyCapacity(array $tasks, ?Carbon $now = null): int
    {
        return max(0, $this->getMonthlyLimit() - $this->getRolling30dUsage($tasks, $now));
    }

    /**
     * @param  array<JulesTask>  $tasks
     */
    public function getAvailableConcurrencySlots(array $tasks): int
    {
        return max(0, $this->getConcurrencyLimit() - $this->getActiveCount($tasks));
    }

    /**
     * @param  array<JulesTask>  $tasks
     */
    public function canDispatch(array $tasks, ?Carbon $now = null): bool
    {
        return $this->getRemainingDailyCapacity($tasks, $now) > 0
            && $this->getRemainingMonthlyCapacity($tasks, $now) > 0
            && $this->getAvailableConcurrencySlots($tasks) > 0;
    }

    /**
     * @param  array<JulesTask>  $tasks
     */
    public function getNextSlotAvailableEstimate(array $tasks, ?Carbon $now = null): ?Carbon
    {
        $now ??= Carbon::now();

        if ($this->canDispatch($tasks, $now)) {
            return $now;
        }

        $estimates = [];

        // 1. If daily quota is exhausted, calculate when window drops below dailyLimit
        if ($this->getRemainingDailyCapacity($tasks, $now) === 0) {
            $cutoff = $now->copy()->subHours(24);
            $dispatchedInWindow = [];

            foreach ($tasks as $task) {
                if ($task->dispatchedAt !== null && $task->dispatchedAt->gte($cutoff)) {
                    $dispatchedInWindow[] = $task->dispatchedAt;
                }
            }

            if (! empty($dispatchedInWindow)) {
                sort($dispatchedInWindow);
                $targetIndex = max(0, count($dispatchedInWindow) - $this->getDailyLimit());
                /** @var Carbon $agingTask */
                $agingTask = $dispatchedInWindow[$targetIndex];

                $estimates[] = $agingTask->copy()->addHours(24);
            }
        }

        // 2. If monthly quota is exhausted, calculate when window drops below monthlyLimit
        if ($this->getRemainingMonthlyCapacity($tasks, $now) === 0) {
            $cutoff = $now->copy()->subDays(30);
            $dispatchedInMonth = [];

            foreach ($tasks as $task) {
                if ($task->dispatchedAt !== null && $task->dispatchedAt->gte($cutoff)) {
                    $dispatchedInMonth[] = $task->dispatchedAt;
                }
            }

            if (! empty($dispatchedInMonth)) {
                sort($dispatchedInMonth);
                $targetIndex = max(0, count($dispatchedInMonth) - $this->getMonthlyLimit());
                /** @var Carbon $agingTask */
                $agingTask = $dispatchedInMonth[$targetIndex];

                $estimates[] = $agingTask->copy()->addDays(30);
            }
        }

        // 3. If concurrency is full, estimate based on oldest active task + avg runtime (15 mins), never in past
        if ($this->getAvailableConcurrencySlots($tasks) === 0) {
            $activeTasks = $this->getActiveTasks($tasks);
            if (! empty($activeTasks)) {
                usort($activeTasks, fn (JulesTask $a, JulesTask $b) => ($a->dispatchedAt?->timestamp ?? 0) <=> ($b->dispatchedAt?->timestamp ?? 0));
                $oldestActive = $activeTasks[0];
                $concurrencyEst = $oldestActive->dispatchedAt ? $oldestActive->dispatchedAt->copy()->addMinutes(15) : $now->copy()->addMinutes(15);

                if ($concurrencyEst->lte($now)) {
                    $concurrencyEst = $now->copy()->addMinutes(2);
                }

                $estimates[] = $concurrencyEst;
            }
        }

        if (empty($estimates)) {
            return $now;
        }

        // The earliest time when ALL capacity constraints are satisfied is the max of the individual estimates
        usort($estimates, fn (Carbon $a, Carbon $b) => $b->timestamp <=> $a->timestamp);

        return $estimates[0];
    }

    /**
     * @param  array<JulesTask>  $tasks
     * @return array{
     *     tier: string,
     *     tier_label: string,
     *     daily_limit: int,
     *     daily_used: int,
     *     daily_remaining: int,
     *     daily_utilization_pct: float,
     *     monthly_limit: int,
     *     monthly_used: int,
     *     monthly_remaining: int,
     *     monthly_utilization_pct: float,
     *     concurrency_limit: int,
     *     concurrency_active: int,
     *     concurrency_available: int,
     *     concurrency_utilization_pct: float,
     *     can_dispatch: bool,
     *     next_slot_available_at: ?string,
     *     queued_count: int,
     *     completed_count: int
     * }
     */
    public function getMetrics(array $tasks, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $dailyUsed = $this->getRolling24hUsage($tasks, $now);
        $dailyLimit = $this->getDailyLimit();
        $remainingDaily = $this->getRemainingDailyCapacity($tasks, $now);
        $monthlyUsed = $this->getRolling30dUsage($tasks, $now);
        $monthlyLimit = $this->getMonthlyLimit();
        $remainingMonthly = $this->getRemainingMonthlyCapacity($tasks, $now);
        $activeCount = $this->getActiveCount($tasks);
        $concurrencyLimit = $this->getConcurrencyLimit();
        $availableSlots = $this->getAvailableConcurrencySlots($tasks);
        $nextSlot = $this->getNextSlotAvailableEstimate($tasks, $now);

        $queuedCount = 0;
        $completedCount = 0;
        foreach ($tasks as $task) {
            if ($task->isQueued()) {
                $queuedCount++;
            } elseif ($task->status === JulesTask::STATUS_COMPLETED) {
                $completedCount++;
            }
        }

        return [
            'tier' => $this->tier->value,
            'tier_label' => $this->tier->label(),
            'daily_limit' => $dailyLimit,
            'daily_used' => $dailyUsed,
            'daily_remaining' => $remainingDaily,
            'daily_utilization_pct' => round(($dailyUsed / max(1, $dailyLimit)) * 100, 1),
            'monthly_limit' => $monthlyLimit,
            'monthly_used' => $monthlyUsed,
            'monthly_remaining' => $remainingMonthly,
            'monthly_utilization_pct' => round(($monthlyUsed / max(1, $monthlyLimit)) * 100, 1),
            'concurrency_limit' => $concurrencyLimit,
            'concurrency_active' => $activeCount,
            'concurrency_available' => $availableSlots,
            'concurrency_utilization_pct' => round(($activeCount / max(1, $concurrencyLimit)) * 100, 1),
            'can_dispatch' => $this->canDispatch($tasks, $now),
            'next_slot_available_at' => $nextSlot?->toIso8601String(),
            'queued_count' => $queuedCount,
            'completed_count' => $completedCount,
        ];
    }

    /**
     * @param  array<JulesTask>  $tasks
     * @return array<string>
     */
    public function getMaximizationRecommendations(array $tasks, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $recommendations = [];
        $metrics = $this->getMetrics($tasks, $now);

        if ($metrics['queued_count'] > 0 && $metrics['concurrency_available'] > 0 && $metrics['daily_remaining'] > 0) {
            $recommendations[] = sprintf(
                'Available capacity: %d concurrent slot(s) and %d daily task quota remaining. Run `php artisan jules:queue dispatch` to process %d pending queued task(s).',
                $metrics['concurrency_available'],
                $metrics['daily_remaining'],
                min($metrics['queued_count'], $metrics['concurrency_available'])
            );
        }

        if ($metrics['concurrency_available'] === 0) {
            $recommendations[] = sprintf(
                'Concurrency saturated (%d/%d active). Next task completion will free a slot. Run `cleanup` if any task has stalled.',
                $metrics['concurrency_active'],
                $metrics['concurrency_limit']
            );
        }

        if ($metrics['daily_remaining'] === 0) {
            $recommendations[] = sprintf(
                'Daily rolling limit reached (100%% utilized). Slot resets at %s. Hold non-urgent tasks in the queue until window opens.',
                $metrics['next_slot_available_at'] ?? 'soon'
            );
        } elseif ($metrics['monthly_remaining'] === 0) {
            $recommendations[] = sprintf(
                'Monthly rolling limit reached (%d/%d used). Slot resets at %s. Hold queued tasks until quota refreshes.',
                $metrics['monthly_used'],
                $metrics['monthly_limit'],
                $metrics['next_slot_available_at'] ?? 'soon'
            );
        } elseif ($metrics['daily_utilization_pct'] < 20.0) {
            $recommendations[] = sprintf(
                'Quota headroom available (%d daily tasks, %d monthly tasks remaining). Schedule automated nightly background tasks (e.g. endpoint sweeps, adapter checks, test suite enhancements) to maximize quota.',
                $metrics['daily_remaining'],
                $metrics['monthly_remaining']
            );
        }

        if ($this->tier === JulesTier::Free && $metrics['queued_count'] >= 5) {
            $recommendations[] = 'High queue depth on Free Tier detected. Upgrading to Jules Pro provides 100 tasks/day and 15 concurrent slots.';
        }

        return $recommendations;
    }
}
