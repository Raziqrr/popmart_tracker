<?php

use App\Models\PopmartAccountPoints;
use App\Models\PopmartAction;
use App\Services\PopMart\LuckyPoints;
use Illuminate\Support\Facades\Http;
use Tests\Support\Make;

function tasksResponse(array $extra = []): array
{
    return ['json' => [
        'tasks' => [['id' => 't1', 'alias' => 'login'], ['id' => 't2', 'alias' => 'buy']],
        'userTasks' => [['taskId' => 't1', 'done' => true, 'draw' => false], ['taskId' => 't2', 'done' => true, 'draw' => false]],
        ...$extra,
    ]];
}

it('syncs the balance from the daily-task response (assumed field names)', function () {
    $account = Make::account(Make::user());
    Http::fake([
        '*public_getTasksByActivityAndChannel*' => Http::sequence()
            ->push(tasksResponse(['gauge' => ['current' => 2480, 'max' => 500]]))
            ->push(tasksResponse(['gauge' => ['current' => 2600, 'max' => 500]])),
    ]);

    $points = app(LuckyPoints::class)->sync($account);

    expect($points->balance)->toBe(2480)
        ->and($points->redeem_minimum)->toBe(500)
        ->and(PopmartAccountPoints::count())->toBe(1);

    // A second sync updates the same row.
    app(LuckyPoints::class)->sync($account);

    expect(PopmartAccountPoints::count())->toBe(1)
        ->and(PopmartAccountPoints::first()->balance)->toBe(2600);
});

it('stores nothing when the response has no points', function () {
    $account = Make::account(Make::user());
    Http::fake(['*public_getTasksByActivityAndChannel*' => tasksResponse()]);

    expect(app(LuckyPoints::class)->sync($account))->toBeNull()
        ->and(PopmartAccountPoints::count())->toBe(0);
});

it('serves the latest balance to its owner, with the session state', function () {
    $user = Make::user();
    $account = Make::account($user);
    PopmartAccountPoints::create(['popmart_account_id' => $account->id, 'balance' => 2480, 'redeem_minimum' => 500, 'synced_at' => now()]);

    $this->actingAs($user)->getJson('/api/lucky-points')->assertOk()
        ->assertJsonPath('balance', 2480)
        ->assertJsonPath('redeem_minimum', 500)
        ->assertJsonPath('session_valid', true);

    $account->update(['session_expires_at' => now()->subMinute()]);
    $this->actingAs($user)->getJson('/api/lucky-points')->assertJsonPath('session_valid', false);

    // Someone else sees nothing of it: the endpoint answers null / empty.
    expect($this->actingAs(Make::user())->getJson('/api/lucky-points')->assertOk()->json())->toBeEmpty();
});

it('claims finished tasks, skips the purchase one, and returns the refreshed balance', function () {
    $user = Make::user();
    $account = Make::account($user);
    Http::fake([
        '*get-session*' => Http::response(['session' => ['userId' => $account->popmart_member_id]]),
        '*public_getTasksByActivityAndChannel*' => tasksResponse(['gauge' => ['current' => 90, 'max' => 500]]),
        '*task/draw*' => Http::response(['json' => ['rewards' => ['points' => 10]]]),
    ]);

    $response = $this->actingAs($user)->postJson("/api/popmart-accounts/{$account->id}/claim-tasks")->assertOk();

    expect(collect($response->json('results'))->pluck('status', 'alias')->all())->toBe(['login' => 'claimed', 'buy' => 'skipped_excluded'])
        ->and($response->json('points.balance'))->toBe(90);
});

describe('redeeming', function () {
    it('answers not available until the redeem call is mapped, and audits the attempt', function () {
        $user = Make::user();
        $account = Make::account($user);

        $this->actingAs($user)->postJson("/api/popmart-accounts/{$account->id}/redeem-points", [], ['Idempotency-Key' => 'k-1'])
            ->assertStatus(501)
            ->assertJsonPath('error.code', 'not_available');

        $action = PopmartAction::first();
        expect($action->action)->toBe('redeem_points')
            ->and($action->user_id)->toBe($user->id)
            ->and($action->status)->toBe('failed')
            ->and($action->idempotency_key)->toBe('k-1');
    });

    it('replays a failed key as a failure, never as a success', function () {
        $user = Make::user();
        $account = Make::account($user);
        $url = "/api/popmart-accounts/{$account->id}/redeem-points";

        $this->actingAs($user)->postJson($url, [], ['Idempotency-Key' => 'k-1']);
        $replay = $this->actingAs($user)->postJson($url, [], ['Idempotency-Key' => 'k-1']);

        $replay->assertStatus(409)->assertJsonPath('error.code', 'action_failed_earlier')->assertJsonPath('replayed', true);
        expect(PopmartAction::count())->toBe(1);
    });

    it('needs an Idempotency-Key', function () {
        $user = Make::user();
        $account = Make::account($user);

        $this->actingAs($user)->postJson("/api/popmart-accounts/{$account->id}/redeem-points")->assertStatus(422);
        expect(PopmartAction::count())->toBe(0);
    });

    it('limits how often one user can try', function () {
        $user = Make::user();
        $account = Make::account($user);
        $url = "/api/popmart-accounts/{$account->id}/redeem-points";

        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->postJson($url, [], ['Idempotency-Key' => "k-{$i}"])->assertStatus(501);
        }

        $this->actingAs($user)->postJson($url, [], ['Idempotency-Key' => 'k-6'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'popmart_throttled');
    });
});
