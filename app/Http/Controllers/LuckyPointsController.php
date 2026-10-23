<?php

namespace App\Http\Controllers;

use App\Models\PopmartAccount;
use App\Models\PopmartAccountPoints;
use App\Models\PopmartAction;
use App\Services\PopMart\AccountActions;
use App\Services\PopMart\LuckyPoints;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lucky points for the dashboard tile. The response is LuckyPoints in
 * resources/js/types/account.ts, or null when nothing is synced yet.
 */
class LuckyPointsController extends Controller
{
    /** GET /api/lucky-points (capability:lucky_points): the latest synced balance, even if the session has since expired. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('popmartAccounts');
        $account = $user->livePopmartAccount() ?? $user->popmartAccounts->sortByDesc('last_synced_at')->first();
        $points = $account ? PopmartAccountPoints::where('popmart_account_id', $account->id)->first() : null;

        return response()->json($points ? self::present($points, $account) : null);
    }

    /**
     * POST /api/popmart-accounts/{account}/redeem-points (capability:redeem_points).
     * Needs an Idempotency-Key header: a retry or double click replays the first answer.
     */
    public function redeem(PopmartAccount $popmartAccount, Request $request, AccountActions $actions, LuckyPoints $luckyPoints): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key');
        $validated = $request->validate(['points' => ['nullable', 'integer', 'min:1']]);
        abort_if($key === '' || strlen($key) > 100, 422, 'Send an Idempotency-Key header (1–100 characters).');

        $result = $actions->run(
            $request->user(), $popmartAccount, 'redeem_points', $key, $validated,
            fn () => $luckyPoints->redeem($popmartAccount, $validated['points'] ?? 0),
        );

        $action = $result['action'];

        // A key that already failed replays the failure, never a success. Retry with a new key.
        if ($action->status === PopmartAction::FAILED) {
            return response()->json([
                'replayed' => true,
                'error' => [
                    'code' => 'action_failed_earlier',
                    'message' => ($action->response['message'] ?? 'That request failed earlier').'. Retry with a new Idempotency-Key.',
                ],
            ], 409);
        }

        return response()->json(['replayed' => $result['replayed'], ...($action->response ?? [])]);
    }

    /** @return array<string, mixed> LuckyPoints */
    public static function present(PopmartAccountPoints $points, PopmartAccount $account): array
    {
        return [
            'balance' => $points->balance,
            'redeem_minimum' => $points->redeem_minimum,
            'expiring' => $points->expiring_points !== null && $points->expiring_at !== null
                ? ['points' => $points->expiring_points, 'expires_at' => $points->expiring_at->toIso8601String()]
                : null,
            'synced_at' => $points->synced_at->toIso8601String(),
            'session_valid' => $account->sessionIsValid(),
        ];
    }
}
