import type { LuckyPoints } from '@/types/account';
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

/** Simulated failing redeem call, for the error state. */
export const fakeRedeemFailure = () => new Promise<void>((_, reject) => setTimeout(() => reject(new Error('Pop Mart rejected the request')), 900));
