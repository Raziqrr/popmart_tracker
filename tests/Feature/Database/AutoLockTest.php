<?php

use App\Jobs\RunAutoLockRule;
use App\Models\AutoLockAttempt;
use App\Models\AutoLockRule;
use App\Models\PopNowBoxHint;
use App\Models\ProductEvent;
use App\Models\UserCheckout;
use App\Services\AutoLock\AutoLockRunner;
use App\Services\AutoLock\AutoLockTriggers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Make;

/**
 * A draw with figures Owl, Toast and Fridge in a 3-box set. Boxes 01 and 02 are
 * verified NOT to be Owl, so box 03 must be Owl: its chance for Owl is 1.
 *
 * @return array{user: User, account: PopmartAccount, product: Product, set: PopNowSet, boxes: list<PopNowBox>, owl: Sku}
 */
function lockWorld(): array
{
    $user = Make::user();
    $account = Make::account($user);
    $product = Make::product(['business_type' => 'draw'], ['Owl', 'Toast', 'Fridge']);
    [$set, $boxes] = Make::set($product, 3);
    $owl = $product->skus->firstWhere('name', 'Owl');

    foreach ([$boxes[0], $boxes[1]] as $box) {
        PopNowBoxHint::create(['pop_now_box_id' => $box->id, 'sku_id' => $owl->id, 'source' => 'verified_api', 'confirmations' => 1]);
    }

    return compact('user', 'account', 'product', 'set', 'boxes', 'owl');
}

function ruleFor(array $world, array $attributes = [], ?int $count = 1): AutoLockRule
{
    $rule = AutoLockRule::create([
        'user_id' => $world['user']->id,
        'popmart_account_id' => $world['account']->id,
        'product_id' => $world['product']->id,
        'target_kind' => 'specific',
        'trigger' => 'narrowed',
        'lock_duration_seconds' => 300,
        'checkout_immediately' => true,
        'enabled' => true,
        'acknowledged_at' => now(),
        ...$attributes,
    ]);
    $rule->picks()->create(['sku_id' => $world['owl']->id, 'count' => $count]);

    return $rule;
}

function fakeLockCalls(bool $mine = true): void
{
    Http::fake([
        '*box/enterBox*' => Http::response(['json' => ['lockRemainingSeconds' => 300]]),
        '*set/public_checkSetBoxLock*' => Http::response(['json' => ['isLockedByMe' => $mine, 'lockRemainingSeconds' => $mine ? 290 : 0]]),
        '*box/checkoutValidate*' => Http::response(['json' => ['valid' => true, 'lockRemainingSeconds' => 280]]),
    ]);
}

beforeEach(fn () => config(['apis.popmart.auto_lock_enabled' => true]));

describe('planning', function () {
    it('picks the box that must hold the wanted figure', function () {
        $world = lockWorld();
        $plan = app(AutoLockRunner::class)->plan(ruleFor($world));

        expect($plan['plan'])->toHaveCount(1)
            ->and($plan['plan'][0]['box_id'])->toBe($world['boxes'][2]->id)
            ->and($plan['plan'][0]['chance'])->toBe(1.0);
    });

    it('does not plan a box that is held or sold', function () {
        $world = lockWorld();
        $world['boxes'][2]->update(['is_locked' => true]);

        expect(app(AutoLockRunner::class)->plan(ruleFor($world)))->toBeNull();
    });

    it('waits for a set seen recently', function () {
        $world = lockWorld();
        $world['set']->update(['last_seen_at' => now()->subHour()]);

        expect(app(AutoLockRunner::class)->plan(ruleFor($world)))->toBeNull();
    });

    it('needs certainty for narrowed but only the threshold for chance_reached', function () {
        $world = lockWorld();
        // Only box 01 rules Owl out now: Owl is 50% in boxes 02 and 03, so nothing is certain.
        PopNowBoxHint::where('pop_now_box_id', $world['boxes'][1]->id)->delete();

        expect(app(AutoLockRunner::class)->plan(ruleFor($world)))->toBeNull();

        $plan = app(AutoLockRunner::class)->plan(ruleFor($world, ['trigger' => 'chance_reached', 'min_chance' => 0.5]));
        // A tie goes to the lower position.
        expect($plan['plan'][0]['box_id'])->toBe($world['boxes'][1]->id)
            ->and($plan['plan'][0]['chance'])->toBe(0.5);

        expect(app(AutoLockRunner::class)->plan(ruleFor($world, ['trigger' => 'chance_reached', 'min_chance' => 0.6])))->toBeNull();
    });

    it('ignores user-reported exclusions the rule does not trust', function () {
        $world = lockWorld();
        // Box 02's exclusion is only a single user's report.
        PopNowBoxHint::where('pop_now_box_id', $world['boxes'][1]->id)->update(['source' => 'user_reported']);

        expect(app(AutoLockRunner::class)->plan(ruleFor($world, ['min_confirmations' => 2])))->toBeNull()
            ->and(app(AutoLockRunner::class)->plan(ruleFor($world, ['min_confirmations' => 1])))->not->toBeNull();
    });
});

describe('a run', function () {
    it('locks, confirms, checks out and tells the user', function () {
        fakeLockCalls();
        $world = lockWorld();
        $rule = ruleFor($world);

        $result = app(AutoLockRunner::class)->run($rule, 'event:1');

        expect($result)->toMatchArray(['status' => 'ran', 'locked' => 1, 'failed' => 0, 'checkout' => 'ready']);

        $attempt = AutoLockAttempt::first();
        expect($attempt->status)->toBe('locked')
            ->and($attempt->pop_now_box_id)->toBe($world['boxes'][2]->id)
            ->and($attempt->chance_at_lock)->toBe(1.0)
            ->and($attempt->lock_expires_at->between(now()->addSeconds(280), now()->addSeconds(295)))->toBeTrue();

        $checkout = UserCheckout::first();
        expect($checkout->status)->toBe('ready')
            ->and($checkout->hold_seconds)->toBe(280)
            ->and($checkout->boxes)->toHaveCount(1)
            ->and($checkout->auto_lock_rule_id)->toBe($rule->id);

        // Done: it asked for one box and got it.
        expect($rule->fresh()->locks_made)->toBe(1)
            ->and($rule->fresh()->enabled)->toBeFalse()
            ->and($rule->picks()->first()->locks_made)->toBe(1);

        $note = $world['user']->notifications()->first();
        expect($note->data['kind'])->toBe('auto_lock')
            ->and($note->data['title'])->toBe('1 box held for you')
            ->and($note->data['held'])->toHaveCount(1);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'box/enterBox')
            && data_get($request->data(), 'json.boxNos') === ['03']
            && data_get($request->data(), 'json.spuId') === $world['product']->id);
        // Never the call that would release what it just held.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'assignSet'));
    });

    it('records a box someone else got first as failed, and does not retry it', function () {
        fakeLockCalls(mine: false);
        $world = lockWorld();
        $rule = ruleFor($world);

        $first = app(AutoLockRunner::class)->run($rule, 'event:1');
        $second = app(AutoLockRunner::class)->run($rule->fresh(), 'event:2');

        expect($first)->toMatchArray(['status' => 'ran', 'locked' => 0, 'failed' => 1])
            ->and(AutoLockAttempt::first()->status)->toBe('failed')
            ->and(AutoLockAttempt::first()->error)->toContain('another shopper')
            ->and($rule->fresh()->locks_made)->toBe(0)
            ->and($rule->fresh()->enabled)->toBeTrue()
            ->and($second['status'])->toBe('nothing_to_lock');
    });

    it('fails the attempts cleanly when Pop Mart pauses us', function () {
        Http::fake(['*' => Http::response(null, 429, ['Retry-After' => '30'])]);
        $world = lockWorld();

        $result = app(AutoLockRunner::class)->run(ruleFor($world), 'event:1');

        expect($result)->toMatchArray(['status' => 'ran', 'locked' => 0, 'failed' => 1])
            ->and(AutoLockAttempt::first()->status)->toBe('failed')
            ->and($world['user']->notifications()->first()->data['title'])->toBe('Auto-lock missed');
    });

    it('does nothing for an expired session, a disabled rule or the kill switch', function () {
        Http::fake();
        $world = lockWorld();

        $world['account']->update(['session_expires_at' => now()->subMinute()]);
        expect(app(AutoLockRunner::class)->run(ruleFor($world), 'e'))->toBe(['status' => 'inactive']);

        $world['account']->update(['session_expires_at' => now()->addDay()]);
        expect(app(AutoLockRunner::class)->run(ruleFor($world, ['enabled' => false]), 'e'))->toBe(['status' => 'inactive']);

        config(['apis.popmart.auto_lock_enabled' => false]);
        expect(app(AutoLockRunner::class)->run(ruleFor($world), 'e'))->toBe(['status' => 'disabled']);

        Http::assertNothingSent();
        expect(AutoLockAttempt::count())->toBe(0);
    });
});

describe('triggers', function () {
    it('queues a sale_opens rule once the sale time passes', function () {
        Queue::fake();
        $world = lockWorld();
        $world['product']->update(['sale_start_at' => now()->subMinute()]);
        $rule = ruleFor($world, ['trigger' => 'sale_opens', 'target_kind' => 'random', 'count' => 1]);

        $report = app(AutoLockTriggers::class)->run();

        expect($report)->toMatchArray(['queued' => 1, 'enabled' => true]);
        Queue::assertPushed(RunAutoLockRule::class, fn ($job) => $job->ruleId === $rule->id && str_starts_with($job->eventKey, 'sale:'));
    });

    it('queues a restock rule only for a restock that happens after it starts watching', function () {
        Queue::fake();
        $world = lockWorld();
        $rule = ruleFor($world, ['trigger' => 'restock']);
        ProductEvent::create(['product_id' => $world['product']->id, 'type' => 'restocked', 'occurred_at' => now()]);

        // First run only sets the cursor: history is not replayed.
        expect(app(AutoLockTriggers::class)->run()['queued'])->toBe(0);

        $event = ProductEvent::create(['product_id' => $world['product']->id, 'type' => 'restocked', 'occurred_at' => now()]);
        expect(app(AutoLockTriggers::class)->run()['queued'])->toBe(1);
        Queue::assertPushed(RunAutoLockRule::class, fn ($job) => $job->ruleId === $rule->id && $job->eventKey === "event:{$event->id}");
    });

    it('re-checks odds triggers every run', function () {
        Queue::fake();
        $world = lockWorld();
        ruleFor($world);

        expect(app(AutoLockTriggers::class)->run()['queued'])->toBe(1)
            ->and(app(AutoLockTriggers::class)->run()['queued'])->toBe(1);
    });

    it('queues nothing while switched off, but still expires finished holds', function () {
        Queue::fake();
        config(['apis.popmart.auto_lock_enabled' => false]);
        $world = lockWorld();
        ruleFor($world);
        $attempt = AutoLockAttempt::create([
            'user_id' => $world['user']->id, 'pop_now_box_id' => $world['boxes'][2]->id, 'status' => 'locked',
            'lock_expires_at' => now()->subMinute(), 'idempotency_key' => 'k1',
        ]);

        expect(app(AutoLockTriggers::class)->run())->toBe(['queued' => 0, 'expired' => 1, 'enabled' => false])
            ->and($attempt->fresh()->status)->toBe('expired');
        Queue::assertNothingPushed();
    });
});

describe('rules API', function () {
    function rulePayload(array $world, array $overrides = []): array
    {
        return [
            'product_id' => $world['product']->id,
            'target' => ['kind' => 'specific', 'picks' => [['sku_id' => $world['owl']->id, 'count' => 1]], 'min_confirmations' => 1, 'verified_only' => false],
            'trigger' => 'narrowed',
            'lock_duration_seconds' => 300,
            'checkout_immediately' => true,
            'enabled' => true,
            'acknowledged' => true,
            ...$overrides,
        ];
    }

    it('creates a rule for the user\'s own live account and lists it', function () {
        $world = lockWorld();

        $this->actingAs($world['user'])->postJson('/api/auto-lock', rulePayload($world))
            ->assertCreated()
            ->assertJsonPath('target.kind', 'specific')
            ->assertJsonPath('target.picks.0.figure.name', 'Owl')
            ->assertJsonPath('renew', null)
            ->assertJsonPath('trigger', 'narrowed');

        $rule = AutoLockRule::first();
        expect($rule->popmart_account_id)->toBe($world['account']->id)
            ->and($rule->acknowledged_at)->not->toBeNull();

        $this->actingAs($world['user'])->getJson('/api/auto-lock')->assertOk()
            ->assertJsonPath('enabled_on_server', true)
            ->assertJsonCount(1, 'rules');
    });

    it('refuses what cannot work', function (string $case) {
        $world = lockWorld();
        $secretFigure = Make::sku($world['product'], 'secret:Golden');
        $shop = Make::product(['business_type' => 'shop'], ['Plain']);
        $foreign = Make::sku(Make::product([], ['Other']), 'Elsewhere');

        $payload = match ($case) {
            'auto-renew' => rulePayload($world, ['renew' => ['renew_when_seconds_left' => 60, 'max_total_hold_seconds' => 900]]),
            'no risk acknowledgement' => rulePayload($world, ['acknowledged' => false]),
            'not a draw' => rulePayload($world, ['product_id' => $shop->id]),
            'random with an odds trigger' => rulePayload($world, ['target' => ['kind' => 'random', 'count' => 1]]),
            'a secret with an odds trigger' => rulePayload($world, ['target' => ['kind' => 'specific', 'picks' => [['sku_id' => $secretFigure->id, 'count' => 1]]]]),
            'a figure from another product' => rulePayload($world, ['target' => ['kind' => 'specific', 'picks' => [['sku_id' => $foreign->id, 'count' => 1]]]]),
            'chance_reached without a threshold' => rulePayload($world, ['trigger' => 'chance_reached']),
            'a hold longer than Pop Mart allows' => rulePayload($world, ['lock_duration_seconds' => 900]),
        };

        $this->actingAs($world['user'])->postJson('/api/auto-lock', $payload)->assertUnprocessable();
        expect(AutoLockRule::count())->toBe(0);
    })->with([
        'auto-renew', 'no risk acknowledgement', 'not a draw', 'random with an odds trigger',
        'a secret with an odds trigger', 'a figure from another product', 'chance_reached without a threshold', 'a hold longer than Pop Mart allows',
    ]);

    it('lets a user switch off and delete only their own rules', function () {
        $world = lockWorld();
        $rule = ruleFor($world);
        $other = Make::user();
        Make::account($other);

        $this->actingAs($other)->patchJson("/api/auto-lock/{$rule->id}", ['enabled' => false])->assertNotFound();
        $this->actingAs($other)->deleteJson("/api/auto-lock/{$rule->id}")->assertNotFound();

        $this->actingAs($world['user'])->patchJson("/api/auto-lock/{$rule->id}", ['enabled' => false])->assertOk()->assertJsonPath('enabled', false);
        $this->actingAs($world['user'])->deleteJson("/api/auto-lock/{$rule->id}")->assertOk();
        expect(AutoLockRule::count())->toBe(0);
    });
});
