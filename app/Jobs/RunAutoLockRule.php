<?php

namespace App\Jobs;

use App\Models\AutoLockRule;
use App\Services\AutoLock\AutoLockRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one auto-lock rule for one trigger event (AutoLockRunner). Unique per
 * rule while queued, so a burst of events can't stack runs of the same rule;
 * the attempt idempotency keys stop a retried run from locking twice.
 */
class RunAutoLockRule implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A lock is only worth trying straight away; don't retry minutes later. */
    public int $tries = 1;

    public int $uniqueFor = 60;

    public function __construct(
        public readonly int $ruleId,
        public readonly string $eventKey,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->ruleId;
    }

    public function handle(AutoLockRunner $runner): void
    {
        $rule = AutoLockRule::find($this->ruleId);

        if ($rule !== null) {
            $runner->run($rule, $this->eventKey);
        }
    }
}
