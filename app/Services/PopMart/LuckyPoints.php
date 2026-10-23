<?php

namespace App\Services\PopMart;

use App\Models\PopmartAccount;
use App\Models\PopmartAccountPoints;
use App\Services\Api\PopMart\Endpoints\Auth\GetSession;
use App\Services\Api\PopMart\PopMartError;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\Adapters\LuckyPointsAdapter;
use App\Services\PopMart\Sync\UnexpectedPayload;
use App\Services\PopMartTaskClaimer;
use Illuminate\Support\Facades\Log;

/**
 * Lucky points for a connected account: sync the balance, and redeem.
 *
 * Redeeming is NOT AVAILABLE until Pop Mart's redeem endpoint is captured
 * (docs/tickets/lucky-points.md). The route, idempotency, rate limit and
 * audit log (AccountActions) are in place; only the call itself is missing,
 * so redeem() refuses instead of guessing at an endpoint that moves points.
 */
class LuckyPoints
{
    /** Re-read the balance with the account's session. Returns null when the payload doesn't carry it. */
    public function sync(PopmartAccount $account, bool $background = false): ?PopmartAccountPoints
    {
        try {
            return $account->usingSession(function (string $cookie, Country $country) use ($account, $background) {
                // The member id saved on connect is Pop Mart's userId; ask only if it's missing.
                $userId = $account->popmart_member_id ?? (new GetSession($country))->withSession($cookie)->fetch()->userId;

                $endpoint = (new (LuckyPointsAdapter::endpoint())($country))->withSession($cookie);
                if ($background) {
                    $endpoint->inBackground();
                }

                $json = $endpoint->data(PopMartTaskClaimer::tasksRequest($country, $userId)) ?? [];
                $points = LuckyPointsAdapter::map($json);

                return PopmartAccountPoints::updateOrCreate(
                    ['popmart_account_id' => $account->id],
                    [...$points, 'raw_payload' => $json, 'synced_at' => now()],
                );
            });
        } catch (UnexpectedPayload $e) {
            Log::info('Lucky points: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @throws FeatureNotAvailable always, for now
     * @throws PopMartException
     */
    public function redeem(PopmartAccount $account, int $points): array
    {
        throw new FeatureNotAvailable("Redeeming lucky points isn't available yet: Pop Mart's redeem call hasn't been mapped.");
    }

    /** @return array{synced: int, skipped: int, stopped: ?string} */
    public function syncAll(): array
    {
        $report = ['synced' => 0, 'skipped' => 0, 'stopped' => null];

        $accounts = PopmartAccount::query()
            ->where(fn ($q) => $q->whereNull('session_expires_at')->orWhere('session_expires_at', '>', now()))
            ->when(config('apis.popmart.house_account_id'), fn ($q, $house) => $q->whereKeyNot($house))
            ->get();

        foreach ($accounts as $account) {
            try {
                $this->sync($account, background: true) ? $report['synced']++ : $report['skipped']++;
            } catch (PopMartException $e) {
                if ($e->is(PopMartError::SessionExpired)) {
                    $report['skipped']++;

                    continue;
                }
                if ($e->abortsBatch()) {
                    $report['stopped'] = $e->getMessage();
                    break;
                }
                $report['skipped']++;
            }
        }

        return $report;
    }
}
