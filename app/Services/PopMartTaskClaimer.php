<?php

namespace App\Services;

use App\Models\PopmartAccount;
use App\Services\Api\PopMart\Endpoints\Activity\ClaimTask;
use App\Services\Api\PopMart\Endpoints\Activity\GetTasksByActivityAndChannel;
use App\Services\Api\PopMart\Endpoints\Auth\GetSession;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Locator\Country;

class PopMartTaskClaimer
{
    // The fixed "POP NOW Daily Task" activity — not account-specific.
    private const ACTIVITY_ID = '489fc5e7-f8cb-44c7-8a8c-c95ada9350c8';

    // Never automate this one — it only completes via a real purchase.
    private const EXCLUDED_ALIASES = ['buy'];

    /**
     * Claim every completed-but-unclaimed daily task's reward for this account.
     * Never touches tasks that require a real purchase, and never invents progress —
     * a task only gets claimed here if Pop Mart's own server already marked it done.
     *
     * A task Pop Mart refuses is reported as 'failed' and the rest still run.
     * An expired session, rate limit or outage stops the whole run (PopMartException);
     * tasks claimed before that stay claimed, and running again skips them.
     *
     * @return array<int, array{alias: string, status: 'claimed'|'failed'|'not_eligible'|'skipped_excluded', reward?: mixed, message?: string}>
     *
     * @throws PopMartException
     */
    public function claimEligible(PopmartAccount $account): array
    {
        return $account->usingSession(function (string $cookie, Country $country) {
            $userId = (new GetSession($country))->withSession($cookie)->fetch()->userId;

            $tasks = (new GetTasksByActivityAndChannel($country))
                ->withSession($cookie)
                ->data([
                    'activityId' => self::ACTIVITY_ID,
                    'taskChannel' => 'web',
                    'userId' => $userId,
                    'area' => $country->value,
                ]);

            $aliasByTaskId = collect($tasks['tasks'] ?? [])->pluck('alias', 'id');
            $results = [];

            foreach ($tasks['userTasks'] ?? [] as $userTask) {
                $alias = $aliasByTaskId[$userTask['taskId'] ?? ''] ?? 'unknown';

                if (in_array($alias, self::EXCLUDED_ALIASES, true)) {
                    $results[] = ['alias' => $alias, 'status' => 'skipped_excluded'];

                    continue;
                }

                if (! ($userTask['done'] ?? false) || ($userTask['draw'] ?? false)) {
                    $results[] = ['alias' => $alias, 'status' => 'not_eligible'];

                    continue;
                }

                try {
                    $claim = (new ClaimTask($country))->withSession($cookie)->data(['userTask' => $userTask]);
                } catch (PopMartException $e) {
                    if ($e->abortsBatch()) {
                        throw $e;
                    }

                    $results[] = ['alias' => $alias, 'status' => 'failed', 'message' => $e->getMessage()];

                    continue;
                }

                $results[] = ['alias' => $alias, 'status' => 'claimed', 'reward' => $claim['rewards'] ?? null];
            }

            return $results;
        });
    }
}
