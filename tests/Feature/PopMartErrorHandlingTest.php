<?php

use App\Models\PopmartAccount;
use App\Services\Api\PopMart\Endpoints\Activity\ClaimTask;
use App\Services\Api\PopMart\Endpoints\Auth\GetSession;
use App\Services\Api\PopMart\PopMartError;
use App\Services\Api\PopMart\PopMartException;
use App\Services\PopMartTaskClaimer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        // No database here: skip the call log and the field register.
        'apis.popmart.log_calls' => false,
        'apis.popmart.record_fields' => false,
    ]);
});

function popMartErrorOf(callable $call): PopMartException
{
    try {
        $call();
    } catch (PopMartException $e) {
        return $e;
    }

    throw new RuntimeException('Expected a PopMartException.');
}

it('maps Pop Mart failures to one exception type', function (string $case, PopMartError $expected) {
    Http::fake(['*' => match ($case) {
        '401' => Http::response(null, 401),
        'trpc_unauthorized' => Http::response(['error' => ['json' => ['message' => 'no', 'data' => ['code' => 'UNAUTHORIZED']]]], 200),
        '429' => Http::response(null, 429, ['Retry-After' => '30']),
        '500' => Http::response(['error' => ['json' => ['message' => 'Task already claimed']]], 500),
        'no_json_key' => Http::response(['ok' => true], 200),
        'offline' => Http::failedConnection(),
    }]);

    $error = popMartErrorOf(fn () => (new ClaimTask)->withSession('cookie')->data(['userTask' => []]));

    expect($error->error)->toBe($expected);
})->with([
    ['401', PopMartError::SessionExpired],
    ['trpc_unauthorized', PopMartError::SessionExpired],
    ['429', PopMartError::RateLimited],
    ['500', PopMartError::Rejected],
    ['no_json_key', PopMartError::UnexpectedResponse],
    ['offline', PopMartError::Unreachable],
]);

it('keeps the retry delay and Pop Mart\'s reason', function () {
    Http::fake(['*' => Http::sequence()
        ->push(null, 429, ['Retry-After' => '30'])
        ->push(['error' => ['json' => ['message' => 'Task already claimed']]], 400)]);

    $limited = popMartErrorOf(fn () => (new ClaimTask)->withSession('cookie')->data());
    // A 429 pauses every call (PopMartThrottle); lift it so the second call reaches Pop Mart.
    Cache::flush();
    $rejected = popMartErrorOf(fn () => (new ClaimTask)->withSession('cookie')->data());

    expect($limited->retryAfter)->toBe(30)
        ->and($rejected->getMessage())->toContain('Task already claimed');
});

it('renders as a JSON error our frontend understands', function () {
    $response = PopMartException::rateLimited(30)->render();

    expect($response->getStatusCode())->toBe(429)
        ->and($response->headers->get('Retry-After'))->toBe('30')
        ->and($response->getData(true))->toBe([
            'error' => ['code' => 'popmart_rate_limited', 'message' => 'Pop Mart is rate limiting requests. Try again shortly.', 'retry_after' => 30],
        ]);
});

it('treats an empty get-session as an expired session', function () {
    Http::fake(['*get-session*' => Http::response('null', 200, ['Content-Type' => 'application/json'])]);

    expect(popMartErrorOf(fn () => (new GetSession)->withSession('cookie')->fetch())->error)->toBe(PopMartError::SessionExpired);
});

it('reads the member and expiry from get-session', function () {
    Http::fake(['*get-session*' => Http::response(['session' => ['userId' => 'member-1', 'expiresAt' => '2026-11-01T00:00:00Z']])]);

    $session = (new GetSession)->withSession('cookie')->fetch();

    expect($session->userId)->toBe('member-1')
        ->and($session->expiresAt->toIso8601ZuluString())->toBe('2026-11-01T00:00:00Z');
});

it('reports each task, and keeps going when Pop Mart refuses one', function () {
    Http::fake([
        '*get-session*' => Http::response(['session' => ['userId' => 'member-1']]),
        '*public_getTasksByActivityAndChannel*' => Http::response(['json' => [
            'tasks' => [['id' => 't1', 'alias' => 'login'], ['id' => 't2', 'alias' => 'share'], ['id' => 't3', 'alias' => 'buy'], ['id' => 't4', 'alias' => 'invite']],
            'userTasks' => [
                ['taskId' => 't1', 'done' => true, 'draw' => false],
                ['taskId' => 't2', 'done' => true, 'draw' => false],
                ['taskId' => 't3', 'done' => true, 'draw' => false],
                ['taskId' => 't4', 'done' => false, 'draw' => false],
            ],
        ]]),
        '*task/draw*' => Http::sequence()
            ->push(['json' => ['rewards' => ['points' => 10]]])
            ->push(['error' => ['json' => ['message' => 'Task already claimed']]], 400),
    ]);

    $results = app(PopMartTaskClaimer::class)->claimEligible(new PopmartAccount(['session_cookie' => 'cookie', 'area' => 'MY']));

    expect(collect($results)->pluck('status', 'alias')->all())->toBe([
        'login' => 'claimed',
        'share' => 'failed',
        'buy' => 'skipped_excluded',
        'invite' => 'not_eligible',
    ])->and($results[1]['message'])->toContain('Task already claimed');
});

it('sends the account\'s own area to Pop Mart', function () {
    Http::fake([
        '*get-session*' => Http::response(['session' => ['userId' => 'member-1']]),
        '*' => Http::response(['json' => ['tasks' => [], 'userTasks' => []]]),
    ]);

    app(PopMartTaskClaimer::class)->claimEligible(new PopmartAccount(['session_cookie' => 'cookie', 'area' => 'SG']));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'public_getTasksByActivityAndChannel')
        && $request->header('X-Area') === ['SG']
        && $request['json']['area'] === 'SG');
});

it('does not call Pop Mart for a session already known to be expired', function () {
    Http::fake();

    $account = new PopmartAccount(['session_cookie' => 'cookie', 'session_expires_at' => now()->subMinute()]);

    expect(popMartErrorOf(fn () => app(PopMartTaskClaimer::class)->claimEligible($account))->error)->toBe(PopMartError::SessionExpired);
    Http::assertNothingSent();
});
