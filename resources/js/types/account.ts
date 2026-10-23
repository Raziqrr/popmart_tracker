/**
 * The user's Pop Mart lucky points: GET /api/lucky-points (LuckyPointsController::present),
 * from popmart_account_points. The sync is built but reads guessed Pop Mart fields, and
 * redeeming answers not_available until the redeem call is mapped (docs/tickets/lucky-points.md).
 */
export interface LuckyPoints {
    balance: number;
    /** Smallest amount Pop Mart lets you redeem at once; null when unknown. */
    redeem_minimum: number | null;
    /** Points that expire soonest, if Pop Mart exposes it. */
    expiring?: { points: number; expires_at: string } | null;
    synced_at: string;
    /** False when the linked Pop Mart session has expired (popmart_accounts.session_expires_at). */
    session_valid: boolean;
}

/** One daily task from POST /api/popmart-accounts/{id}/claim-tasks (App\Services\PopMartTaskClaimer). */
export interface TaskClaimResult {
    alias: string;
    status: 'claimed' | 'failed' | 'not_eligible' | 'skipped_excluded';
    /** Pop Mart's reward payload when claimed; shape not pinned down yet. */
    reward?: unknown;
    /** Why Pop Mart refused it, when status is 'failed'. */
    message?: string;
}
