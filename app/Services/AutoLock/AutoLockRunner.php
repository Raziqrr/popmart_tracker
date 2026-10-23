<?php

namespace App\Services\AutoLock;

use App\Contracts\BoxPredictorContract;
use App\Models\AutoLockAttempt;
use App\Models\AutoLockRule;
use App\Models\PopNowBox;
use App\Models\PopNowBoxHint;
use App\Models\PopNowSet;
use App\Models\UserCheckout;
use App\Notifications\AutoLockResult;
use App\Repositories\PopNowBoxRepository;
use App\Services\Api\PopMart\Endpoints\Draw\CheckoutValidate;
use App\Services\Api\PopMart\Endpoints\Draw\CheckSetBoxLock;
use App\Services\Api\PopMart\Endpoints\Draw\EnterBox;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\Adapters\PopNowSetAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Runs one auto-lock rule (docs/tickets/auto-lock.md):
 *
 * 1. Plan from our own database: the product's freshest live set, chances
 *    from the predictor using only the exclusions the rule trusts, then
 *    LockPlanner picks the boxes. Pop Mart isn't called to plan.
 * 2. One attempt row per box, keyed (rule, box, trigger event), so a retried
 *    job never locks a box twice.
 * 3. One EnterBox for all the boxes with the user's session, then
 *    CheckSetBoxLock per box to confirm the hold (EnterBox's own answer
 *    only gives the soonest timer). Never AssignSet: it would drop the holds.
 * 4. Optionally CheckoutValidate, so the user only has to pay (UserCheckout).
 * 5. Count progress, switch the rule off when done, tell the user.
 *
 * Does nothing at all unless apis.popmart.auto_lock_enabled is on.
 */
class AutoLockRunner
{
    /** A set not polled for this long is too stale to lock from. */
    private const FRESH_MINUTES = 10;

    public function __construct(
        private BoxPredictorContract $predictor,
        private PopNowBoxRepository $boxes,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('apis.popmart.auto_lock_enabled', false);
    }

    /**
     * @return array{status: string, locked?: int, failed?: int, checkout?: string|null}
     */
    public function run(AutoLockRule $rule, string $eventKey): array
    {
        if (! self::enabled()) {
            return ['status' => 'disabled'];
        }

        $rule->loadMissing(['account', 'product', 'picks.sku']);
        if (! $rule->isActive()) {
            return ['status' => 'inactive'];
        }

        $planned = $this->plan($rule);
        if ($planned === null) {
            return ['status' => 'nothing_to_lock'];
        }

        ['set' => $set, 'plan' => $plan, 'boxes' => $boxesById] = $planned;
        $attempts = $this->createAttempts($rule, $plan, $eventKey);
        if ($attempts->isEmpty()) {
            return ['status' => 'already_tried'];
        }

        $checkout = null;

        try {
            $rule->account->usingSession(function (string $cookie, Country $country) use ($rule, $set, $attempts, $boxesById, &$checkout) {
                $boxNos = $attempts->map(fn (AutoLockAttempt $a) => $boxesById[$a->pop_now_box_id]->box_no)->values()->all();
                $lockResponse = (new EnterBox($country))->withSession($cookie)->data(PopNowSetAdapter::boxesRequest($rule->product_id, $set->set_no, $boxNos));

                foreach ($attempts as $attempt) {
                    $boxNo = $boxesById[$attempt->pop_now_box_id]->box_no;
                    $lock = PopNowSetAdapter::mapLock(
                        (new CheckSetBoxLock($country))->withSession($cookie)->data(PopNowSetAdapter::lockRequest($rule->product_id, $set->set_no, $boxNo)) ?? []
                    );
                    $held = $lock['is_mine'] ?? $lock['is_locked'];

                    $attempt->update($held ? [
                        'status' => AutoLockAttempt::LOCKED,
                        'locked_at' => now(),
                        'lock_expires_at' => now()->addSeconds($lock['lock_remaining_seconds'] ?? $rule->lock_duration_seconds),
                        'raw_response' => ['enter_box' => $lockResponse, 'check' => $lock],
                    ] : [
                        'status' => AutoLockAttempt::FAILED,
                        'error' => 'Not held after locking: another shopper probably got it first.',
                        'raw_response' => ['enter_box' => $lockResponse, 'check' => $lock],
                    ]);
                }

                $locked = $attempts->where('status', AutoLockAttempt::LOCKED);
                if ($rule->checkout_immediately && $locked->isNotEmpty()) {
                    $checkout = $this->checkout($rule, $set, $locked, $boxesById, $cookie, $country);
                }
            });
        } catch (PopMartException $e) {
            $attempts->where('status', AutoLockAttempt::PENDING)
                ->each(fn (AutoLockAttempt $a) => $a->update(['status' => AutoLockAttempt::FAILED, 'error' => $e->getMessage()]));
        }

        $locked = $attempts->where('status', AutoLockAttempt::LOCKED);
        $this->recordProgress($rule, $locked);

        Notification::send($rule->user, new AutoLockResult($rule, $set, $attempts, $checkout));

        return [
            'status' => 'ran',
            'locked' => $locked->count(),
            'failed' => $attempts->count() - $locked->count(),
            'checkout' => $checkout?->status,
        ];
    }

    /**
     * The boxes to lock now, from the freshest live set that has any.
     *
     * @return array{set: PopNowSet, plan: list<array{box_id: int, sku_id: string|null, chance: float|null}>, boxes: array<int, PopNowBox>}|null
     */
    public function plan(AutoLockRule $rule): ?array
    {
        $rule->loadMissing('picks.sku');
        $tried = AutoLockAttempt::where('auto_lock_rule_id', $rule->id)->pluck('pop_now_box_id')->all();

        $sets = PopNowSet::where('product_id', $rule->product_id)
            ->where('last_seen_at', '>=', now()->subMinutes(self::FRESH_MINUTES))
            ->orderByDesc('last_seen_at')
            ->get();

        foreach ($sets as $set) {
            $boxes = $this->boxes->boxesForSet($set)->each(
                fn (PopNowBox $box) => $box->setRelation('hints', $box->hints->filter(fn (PopNowBoxHint $hint) => self::trusts($rule, $hint))->values())
            );
            $predictions = $this->predictor->predictBoxes($set, $boxes);

            $free = $boxes
                ->reject(fn (PopNowBox $box) => $box->is_locked || $box->status === 'sold' || in_array($box->id, $tried, true))
                ->map(fn (PopNowBox $box) => [
                    'box_id' => $box->id,
                    'position' => $box->position,
                    'chances' => $rule->picks->mapWithKeys(fn ($pick) => [
                        $pick->sku_id => isset($predictions[$box->id]) ? $predictions[$box->id]->probabilityFor($pick->sku) : 0.0,
                    ])->all(),
                ])
                ->values()
                ->all();

            $plan = LockPlanner::plan(self::spec($rule), $free);
            if ($plan !== []) {
                return ['set' => $set, 'plan' => $plan, 'boxes' => $boxes->keyBy('id')->all()];
            }
        }

        return null;
    }

    /** The rule as LockPlanner reads it. */
    public static function spec(AutoLockRule $rule): array
    {
        return [
            'kind' => $rule->target_kind,
            'owed' => $rule->boxesOwed(),
            'trigger' => $rule->trigger,
            'min_chance' => $rule->min_chance,
            'picks' => $rule->picks->map(fn ($pick) => [
                'sku_id' => $pick->sku_id,
                'owed' => max(0, (int) $pick->count - $pick->locks_made),
                'priority' => $pick->priority,
            ])->values()->all(),
        ];
    }

    /** Verified exclusions always count; user-reported ones need enough confirmations, unless the rule trusts verified only. */
    public static function trusts(AutoLockRule $rule, PopNowBoxHint $hint): bool
    {
        if ($hint->source === 'verified_api') {
            return true;
        }

        return ! $rule->verified_only && $hint->confirmations >= $rule->min_confirmations;
    }

    /** @return Collection<int, AutoLockAttempt> only the attempts this call created (retries skip existing keys) */
    private function createAttempts(AutoLockRule $rule, array $plan, string $eventKey): Collection
    {
        $created = collect();

        foreach ($plan as $item) {
            // Chance triggers fire every minute: one attempt per box per rule, ever.
            $key = in_array($rule->trigger, AutoLockRule::CHANCE_TRIGGERS, true)
                ? "rule:{$rule->id}:box:{$item['box_id']}"
                : "rule:{$rule->id}:box:{$item['box_id']}:{$eventKey}";

            $attempt = AutoLockAttempt::firstOrCreate(['idempotency_key' => $key], [
                'auto_lock_rule_id' => $rule->id,
                'user_id' => $rule->user_id,
                'pop_now_box_id' => $item['box_id'],
                'sku_id' => $item['sku_id'],
                'chance_at_lock' => $item['chance'],
                'status' => AutoLockAttempt::PENDING,
                'attempted_at' => now(),
            ]);

            if ($attempt->wasRecentlyCreated) {
                $created->push($attempt);
            }
        }

        return $created;
    }

    private function checkout(AutoLockRule $rule, PopNowSet $set, Collection $locked, array $boxesById, string $cookie, Country $country): UserCheckout
    {
        $checkout = UserCheckout::create([
            'user_id' => $rule->user_id,
            'popmart_account_id' => $rule->popmart_account_id,
            'pop_now_set_id' => $set->id,
            'auto_lock_rule_id' => $rule->id,
            'status' => UserCheckout::STATUS_PENDING,
        ]);
        $checkout->boxes()->attach($locked->pluck('pop_now_box_id')->all());

        $boxNos = $locked->map(fn (AutoLockAttempt $a) => $boxesById[$a->pop_now_box_id]->box_no)->values()->all();

        try {
            $json = (new CheckoutValidate($country))->withSession($cookie)
                ->data(PopNowSetAdapter::boxesRequest($rule->product_id, $set->set_no, $boxNos, ['pageType' => 'checkout'])) ?? [];

            ($json['valid'] ?? false) && isset($json['lockRemainingSeconds'])
                ? $checkout->markReady((int) $json['lockRemainingSeconds'], $json)
                : $checkout->markFailed(UserCheckout::FAILED_INVALID, $json);
        } catch (PopMartException $e) {
            $checkout->markFailed(UserCheckout::FAILED_INVALID, ['error' => $e->error->value, 'message' => $e->getMessage()]);
        }

        return $checkout;
    }

    private function recordProgress(AutoLockRule $rule, Collection $locked): void
    {
        if ($locked->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($rule, $locked) {
            $rule->increment('locks_made', $locked->count());

            foreach ($locked->whereNotNull('sku_id')->countBy('sku_id') as $skuId => $count) {
                $rule->picks()->where('sku_id', $skuId)->increment('locks_made', $count);
            }

            $rule->refresh()->load('picks');
            if ($rule->boxesOwed() === 0) {
                $rule->update(['enabled' => false]);
            }
        });
    }
}
