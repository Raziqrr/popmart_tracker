<?php

namespace App\Services;

use App\Models\PopmartAccount;
use App\Services\Api\PopMart\Endpoints\Activity\ClaimTask;
use App\Services\Api\PopMart\Endpoints\Activity\GetTasksByActivityAndChannel;
use App\Services\Api\PopMart\Endpoints\Auth\GetSession;

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
     * @return array<int, array{alias: string, status: string, reward?: array}>
     */
    public function claimEligible(PopmartAccount $account): array
    {
        $session = (new GetSession)->withSession($account->session_cookie)->send()->json();
        $userId = $session['session']['userId'];

        $tasks = (new GetTasksByActivityAndChannel)
            ->withSession($account->session_cookie)
            ->data([
                'activityId' => self::ACTIVITY_ID,
                'taskChannel' => 'web',
                'userId' => $userId,
                'area' => $account->area ?? 'MY',
            ]);

        $aliasByTaskId = collect($tasks['tasks'] ?? [])->pluck('alias', 'id');
        $results = [];

        foreach ($tasks['userTasks'] ?? [] as $userTask) {
            $alias = $aliasByTaskId[$userTask['taskId']] ?? 'unknown';

            if (in_array($alias, self::EXCLUDED_ALIASES, true)) {
                $results[] = ['alias' => $alias, 'status' => 'skipped_excluded'];

                continue;
            }

            if (! $userTask['done'] || $userTask['draw']) {
                $results[] = ['alias' => $alias, 'status' => 'not_eligible'];

                continue;
            }

            $claim = (new ClaimTask)->withSession($account->session_cookie)->data(['userTask' => $userTask]);

            $results[] = ['alias' => $alias, 'status' => 'claimed', 'reward' => $claim['rewards'] ?? null];
        }

        return $results;
    }
}
