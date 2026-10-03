/**
 * The user's Pop Mart lucky points, synced through their connected popmart_accounts row.
 * NOT IMPLEMENTED on the backend: there's no points column or sync/redeem job yet.
 * See docs/tickets/lucky-points.md. Field names are a proposal to settle in that ticket.
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
