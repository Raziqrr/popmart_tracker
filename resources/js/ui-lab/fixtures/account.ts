import { ApiError } from '@/lib/api';
import { CAPABILITIES } from '@/lib/capabilities';
import type { AuthState, Capability } from '@/lib/capabilities';
import type { LuckyPoints, TaskClaimResult } from '@/types/account';
import { daysAgo, hoursFromNow, minutesAgo } from './time';

// SYNC: follows the proposed LuckyPoints shape in types/account.ts (docs/tickets/lucky-points.md).
export const sampleLuckyPoints: LuckyPoints = {
    balance: 2480,
    redeem_minimum: 500,
    expiring: { points: 300, expires_at: hoursFromNow(24 * 6) },
    synced_at: minutesAgo(12),
    session_valid: true,
};

export const sampleLuckyPointsStates: { label: string; note: string; points: LuckyPoints | null }[] = [
    { label: 'Redeemable', note: 'balance ≥ minimum, session valid', points: sampleLuckyPoints },
    { label: 'Below minimum', note: 'Redeem disabled', points: { ...sampleLuckyPoints, balance: 320, expiring: null } },
    { label: 'Session expired', note: 'popmart_accounts.session_expires_at passed', points: { ...sampleLuckyPoints, session_valid: false, synced_at: daysAgo(3) } },
    { label: 'Not connected', note: 'No popmart_accounts row', points: null },
];

/** Simulated redeem call: succeeds after a short delay. */
export const fakeRedeem = () => new Promise<void>((resolve) => setTimeout(resolve, 900));

const later = <T,>(settle: (resolve: (value: T) => void, reject: (error: unknown) => void) => void) =>
    new Promise<T>((resolve, reject) => setTimeout(() => settle(resolve, reject), 900));

/** Simulated failing redeem call, for the error state. */
export const fakeRedeemFailure = () =>
    later<void>((_, reject) => reject(new ApiError('popmart_rejected', 'Pop Mart refused the request: points are locked during a draw.', 502)));

/** Simulated claim-tasks response: two claimed, one Pop Mart refused, the rest not ready. */
export const fakeClaimTasks = () =>
    later<TaskClaimResult[]>((resolve) =>
        resolve([
            { alias: 'login', status: 'claimed', reward: { points: 10 } },
            { alias: 'share', status: 'claimed', reward: { points: 20 } },
            { alias: 'browse', status: 'failed', message: 'Pop Mart refused the request: task already claimed.' },
            { alias: 'invite', status: 'not_eligible' },
            { alias: 'buy', status: 'skipped_excluded' },
        ]),
    );

const member = { id: 1, name: 'Test User', email: 'test@example.com' };
const memberCapabilities: Capability[] = ['connect_popmart', 'watchlist', 'report_hints'];

/** GET /api/me for each access level (App\Http\Controllers\MeController). */
export const sampleAuthStates: { label: string; note: string; auth: AuthState }[] = [
    { label: 'Guest', note: 'Not logged in → "Log in to …"', auth: { user: null, popmart: null, capabilities: [] } },
    { label: 'Logged in, no Pop Mart', note: 'Member only → "Connect Pop Mart to …"', auth: { user: member, popmart: null, capabilities: memberCapabilities } },
    {
        label: 'Pop Mart session expired',
        note: 'Connected, session_valid false → "Reconnect Pop Mart to …"',
        auth: { user: member, popmart: { account_id: 9, session_valid: false, session_expires_at: daysAgo(1) }, capabilities: memberCapabilities },
    },
    {
        label: 'Logged in on Pop Mart',
        note: 'Live session → the action shows',
        auth: {
            user: member,
            popmart: { account_id: 9, session_valid: true, session_expires_at: hoursFromNow(24 * 20) },
            capabilities: Object.keys(CAPABILITIES) as Capability[],
        },
    },
];

/** Each way a Pop Mart call can fail, as our API reports it (see lib/api.ts). */
export const sampleApiErrors: { label: string; note: string; error: ApiError }[] = [
    { label: 'Not logged in', note: '401 unauthenticated → log in', error: new ApiError('unauthenticated', 'Log in to claim daily tasks.', 401) },
    {
        label: 'Pop Mart not connected',
        note: '409 popmart_not_connected → connect',
        error: new ApiError('popmart_not_connected', 'Connect your Pop Mart account to claim daily tasks.', 409),
    },
    {
        label: 'Session expired',
        note: '409 popmart_session_expired → reconnect',
        error: new ApiError('popmart_session_expired', 'Your Pop Mart session has expired. Reconnect your account.', 409),
    },
    {
        label: 'Rate limited',
        note: '429 popmart_rate_limited, Retry-After 30',
        error: new ApiError('popmart_rate_limited', 'Pop Mart is rate limiting requests. Try again shortly.', 429, 30),
    },
    {
        label: 'Pop Mart down',
        note: '503 popmart_unreachable → retry',
        error: new ApiError('popmart_unreachable', 'Could not reach Pop Mart. Try again shortly.', 503),
    },
    { label: 'Offline', note: 'fetch() itself failed', error: new ApiError('network', 'Could not reach Popmart Tracker. Check your connection.') },
];

/** Claim call that fails with the given error. */
export const fakeClaimFailure = (error: ApiError) => () => later<TaskClaimResult[]>((_, reject) => reject(error));
