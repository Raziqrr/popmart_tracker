<?php

namespace App\Services\PopMart;

use App\Models\PopmartAccount;
use App\Models\PopmartAction;
use App\Models\User;
use App\Services\Api\PopMart\PopMartException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * The one way to act on a user's real Pop Mart account (redeem, lock,
 * checkout): every action is
 *
 * - idempotent: the client's Idempotency-Key is unique per user and action,
 *   so a double click or retry replays the first result instead of acting twice
 * - rate limited per user and action (MAX_PER_MINUTE)
 * - audited in popmart_actions: who, which account, what was asked, what
 *   Pop Mart answered
 */
class AccountActions
{
    private const MAX_PER_MINUTE = 5;

    /**
     * @param  callable(): array  $act  does the Pop Mart calls; returns what to store and send back
     * @return array{replayed: bool, action: PopmartAction}
     *
     * @throws PopMartException
     * @throws ActionInProgress when the same key is still running
     */
    public function run(User $user, PopmartAccount $account, string $action, string $idempotencyKey, array $request, callable $act): array
    {
        $existing = $this->find($user, $action, $idempotencyKey);
        if ($existing !== null) {
            return $this->replay($existing);
        }

        $limiterKey = "popmart-action:{$user->id}:{$action}";
        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_PER_MINUTE)) {
            throw PopMartException::throttled(RateLimiter::availableIn($limiterKey));
        }
        RateLimiter::hit($limiterKey, 60);

        try {
            $record = PopmartAction::create([
                'user_id' => $user->id,
                'popmart_account_id' => $account->id,
                'action' => $action,
                'idempotency_key' => $idempotencyKey,
                'request' => $request,
                'status' => PopmartAction::PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two requests with the same key raced; the other one owns it.
            return $this->replay($this->find($user, $action, $idempotencyKey));
        }

        try {
            $response = $act();
        } catch (PopMartException $e) {
            $record->update(['status' => PopmartAction::FAILED, 'error' => $e->error->value, 'response' => ['message' => $e->getMessage()]]);

            throw $e;
        } catch (Throwable $e) {
            $record->update(['status' => PopmartAction::FAILED, 'error' => 'exception', 'response' => ['message' => $e->getMessage()]]);

            throw $e;
        }

        $record->update(['status' => PopmartAction::SUCCEEDED, 'response' => $response]);

        return ['replayed' => false, 'action' => $record];
    }

    private function find(User $user, string $action, string $key): ?PopmartAction
    {
        return PopmartAction::where('user_id', $user->id)->where('action', $action)->where('idempotency_key', $key)->first();
    }

    /** @return array{replayed: bool, action: PopmartAction} */
    private function replay(PopmartAction $action): array
    {
        if ($action->status === PopmartAction::PENDING) {
            throw new ActionInProgress;
        }

        return ['replayed' => true, 'action' => $action];
    }
}
