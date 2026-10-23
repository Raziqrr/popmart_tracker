<?php

use App\Models\AutoLockRule;
use App\Models\PopNowBoxHint;
use App\Services\AutoLock\AutoLockRunner;
use App\Services\AutoLock\LockPlanner;

/*
| Auto-lock decisions without a database or Pop Mart: which boxes a rule
| picks, and which exclusions it trusts. The runner's Pop Mart calls aren't
| covered here.
*/

function freeBox(int $id, int $position, array $chances = []): array
{
    return ['box_id' => $id, 'position' => $position, 'chances' => $chances];
}

function lockRule(string $kind, int $owed, string $trigger = 'sale_opens', ?float $minChance = null, array $picks = []): array
{
    return ['kind' => $kind, 'owed' => $owed, 'trigger' => $trigger, 'min_chance' => $minChance, 'picks' => $picks];
}

function boxIds(array $plan): array
{
    return array_column($plan, 'box_id');
}

describe('random', function () {
    it('takes free boxes in grid order, up to what is owed', function () {
        $plan = LockPlanner::plan(lockRule('random', 2), [freeBox(9, 3), freeBox(7, 1), freeBox(8, 2)]);

        expect(boxIds($plan))->toBe([7, 8])
            ->and($plan[0]['sku_id'])->toBeNull();
    });

    it('locks nothing once the rule has what it wanted', function () {
        expect(LockPlanner::plan(lockRule('random', 0), [freeBox(1, 1)]))->toBe([]);
    });
});

describe('specific', function () {
    it('takes each figure\'s best box, never the same box twice', function () {
        $boxes = [
            freeBox(1, 1, ['owl' => 0.6, 'toast' => 0.3]),
            freeBox(2, 2, ['owl' => 0.2, 'toast' => 0.5]),
            freeBox(3, 3, ['owl' => 0.4, 'toast' => 0.1]),
        ];
        $plan = LockPlanner::plan(lockRule('specific', 3, picks: [
            ['sku_id' => 'owl', 'owed' => 2, 'priority' => null],
            ['sku_id' => 'toast', 'owed' => 1, 'priority' => null],
        ]), $boxes);

        expect(array_map(fn ($p) => [$p['box_id'], $p['sku_id']], $plan))->toBe([[1, 'owl'], [3, 'owl'], [2, 'toast']])
            ->and($plan[0]['chance'])->toBe(0.6);
    });

    it('skips boxes that have ruled the figure out', function () {
        $plan = LockPlanner::plan(lockRule('specific', 1, picks: [['sku_id' => 'owl', 'owed' => 1, 'priority' => null]]), [
            freeBox(1, 1, ['owl' => 0.0]),
            freeBox(2, 2, ['owl' => 0.25]),
        ]);

        expect(boxIds($plan))->toBe([2]);
    });
});

describe('ranked', function () {
    it('fills from figure #1 first, then falls back down the list', function () {
        $boxes = [
            freeBox(1, 1, ['owl' => 0.9, 'toast' => 0.1]),
            freeBox(2, 2, ['owl' => 0.0, 'toast' => 0.8]),
            freeBox(3, 3, ['owl' => 0.5, 'toast' => 0.5]),
        ];
        $plan = LockPlanner::plan(lockRule('ranked', 3, 'chance_reached', 0.5, [
            ['sku_id' => 'toast', 'owed' => 0, 'priority' => 2],
            ['sku_id' => 'owl', 'owed' => 0, 'priority' => 1],
        ]), $boxes);

        // owl (#1) takes every box at 50%+ for it, toast (#2) gets what's left.
        expect(array_map(fn ($p) => [$p['box_id'], $p['sku_id']], $plan))->toBe([[1, 'owl'], [3, 'owl'], [2, 'toast']]);
    });

    it('stops at the total even when more boxes qualify', function () {
        $boxes = [freeBox(1, 1, ['owl' => 0.9]), freeBox(2, 2, ['owl' => 0.8])];

        expect(LockPlanner::plan(lockRule('ranked', 1, picks: [['sku_id' => 'owl', 'owed' => 0, 'priority' => 1]]), $boxes))->toHaveCount(1);
    });
});

describe('trigger conditions', function () {
    it('only locks for chance_reached at or above the threshold', function () {
        $plan = LockPlanner::plan(lockRule('specific', 1, 'chance_reached', 0.75, [['sku_id' => 'owl', 'owed' => 1, 'priority' => null]]), [
            freeBox(1, 1, ['owl' => 0.74]),
        ]);

        expect($plan)->toBe([])
            ->and(LockPlanner::qualifies(0.75, 'chance_reached', 0.75))->toBeTrue();
    });

    it('only locks for narrowed when the box is down to that one figure', function () {
        expect(LockPlanner::qualifies(0.95, 'narrowed', null))->toBeFalse()
            ->and(LockPlanner::qualifies(1.0, 'narrowed', null))->toBeTrue()
            ->and(LockPlanner::qualifies(0.01, 'restock', null))->toBeTrue();
    });
});

describe('trusted exclusions', function () {
    $rule = fn (bool $verifiedOnly, int $minConfirmations = 2) => new AutoLockRule(['verified_only' => $verifiedOnly, 'min_confirmations' => $minConfirmations]);
    $hint = fn (string $source, int $confirmations = 1) => new PopNowBoxHint(['source' => $source, 'confirmations' => $confirmations]);

    it('always trusts verified exclusions', function () use ($rule, $hint) {
        expect(AutoLockRunner::trusts($rule(true), $hint('verified_api')))->toBeTrue();
    });

    it('trusts user reports only with enough confirmations, and never when verified-only', function () use ($rule, $hint) {
        expect(AutoLockRunner::trusts($rule(false), $hint('user_reported', 1)))->toBeFalse()
            ->and(AutoLockRunner::trusts($rule(false), $hint('user_reported', 2)))->toBeTrue()
            ->and(AutoLockRunner::trusts($rule(true), $hint('user_reported', 9)))->toBeFalse();
    });
});

it('does nothing while the kill switch is off', function () {
    config(['apis.popmart.auto_lock_enabled' => false]);

    expect(app(AutoLockRunner::class)->run(new AutoLockRule, 'event:1'))->toBe(['status' => 'disabled']);
});
