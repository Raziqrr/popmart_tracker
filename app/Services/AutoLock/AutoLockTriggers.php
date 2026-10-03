<?php

namespace App\Services\AutoLock;

use App\Jobs\RunAutoLockRule;
use App\Models\AutoLockAttempt;
use App\Models\AutoLockRule;
use App\Models\Product;
use App\Models\ProductEvent;
use App\Services\PopMart\Sync\ProductEventType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Decides which rules to run (popnow:auto-lock, every minute) and queues a
 * RunAutoLockRule job for each:
 *
 * - sale_opens: a sale_started event, or the product's sale_start_at passing
 * - restock: a restocked event
 * - chance_reached / narrowed: every run, since any new exclusion can raise a
 *   box's odds; the runner only locks when the plan finds a qualifying box,
 *   and never tries the same box twice for one rule
 *
 * Also marks held boxes expired once their hold has run out.
 * Queues nothing while apis.popmart.auto_lock_enabled is off.
 */
class AutoLockTriggers
{
    private const CURSOR_KEY = 'autolock:last_event_id';

    /** Sale openings older than this are history, not a trigger. */
    private const SALE_WINDOW_MINUTES = 10;

    /** @return array{queued: int, expired: int, enabled: bool} */
    public function run(): array
    {
        $expired = AutoLockAttempt::where('status', AutoLockAttempt::LOCKED)
            ->where('lock_expires_at', '<=', now())
            ->update(['status' => AutoLockAttempt::EXPIRED]);

        if (! AutoLockRunner::enabled()) {
            return ['queued' => 0, 'expired' => $expired, 'enabled' => false];
        }

        $queued = 0;
        foreach ($this->dueRules() as [$ruleId, $eventKey]) {
            RunAutoLockRule::dispatch($ruleId, $eventKey);
            $queued++;
        }

        return ['queued' => $queued, 'expired' => $expired, 'enabled' => true];
    }

    /** @return list<array{0: int, 1: string}> rule id and trigger event key */
    private function dueRules(): array
    {
        $due = [];
        $rules = AutoLockRule::with(['account', 'picks'])
            ->where('enabled', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()
            ->filter(fn (AutoLockRule $rule) => $rule->isActive());

        if ($rules->isEmpty()) {
            return [];
        }

        // Events since the last run (first run: start from now, don't replay history).
        $cursor = Cache::get(self::CURSOR_KEY) ?? ProductEvent::max('id') ?? 0;
        $events = ProductEvent::where('id', '>', $cursor)
            ->whereIn('type', [ProductEventType::SaleStarted->value, ProductEventType::Restocked->value])
            ->whereIn('product_id', $rules->pluck('product_id')->unique())
            ->orderBy('id')
            ->get();
        Cache::forever(self::CURSOR_KEY, max($cursor, (int) ProductEvent::max('id')));

        $saleOpened = Product::whereIn('id', $rules->where('trigger', 'sale_opens')->pluck('product_id'))
            ->whereBetween('sale_start_at', [now()->subMinutes(self::SALE_WINDOW_MINUTES), now()])
            ->pluck('sale_start_at', 'id');

        foreach ($rules as $rule) {
            $key = match ($rule->trigger) {
                'sale_opens' => $this->saleKey($rule, $events, $saleOpened),
                'restock' => ($e = $events->first(fn ($e) => $e->product_id === $rule->product_id && $e->type === ProductEventType::Restocked))
                    ? "event:{$e->id}" : null,
                default => 'chance',
            };

            if ($key !== null) {
                $due[] = [$rule->id, $key];
            }
        }

        return $due;
    }

    private function saleKey(AutoLockRule $rule, $events, $saleOpened): ?string
    {
        $event = $events->first(fn ($e) => $e->product_id === $rule->product_id && $e->type === ProductEventType::SaleStarted);
        if ($event) {
            return "event:{$event->id}";
        }

        // The tag may lag behind the clock: the scheduled start time passing counts too.
        $startsAt = $saleOpened[$rule->product_id] ?? null;

        return $startsAt ? 'sale:'.Carbon::parse($startsAt)->getTimestamp() : null;
    }
}
