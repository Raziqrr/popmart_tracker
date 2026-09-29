import type { AutoLockRule, LockAccount, LockAttempt, LockFigure, PopNowLockLimits } from '@/types/lock';
import { figurePlaceholder } from './placeholder';
import { productBySlug } from './products';
import { daysAgo, hoursAgo, minutesAgo } from './time';

// SYNC: follows the proposed shapes in types/lock.ts (docs/tickets/auto-lock.md).

const midnightSnack = productBySlug('pebble-pals-midnight-snack');
const tinyGarden = productBySlug('pebble-pals-tiny-garden');

/** Figures (SKUs) per POP NOW product, keyed by product id. */
export const sampleFigures: Record<string, LockFigure[]> = {
    [midnightSnack.id]: [
        { sku_id: 'sku-ms-1', name: 'Midnight Noodles', image_url: figurePlaceholder('#cfe8dc', '#2f8f6a'), is_secret: false },
        { sku_id: 'sku-ms-2', name: 'Sleepy Toast', image_url: figurePlaceholder('#f6e3c1', '#b7792b'), is_secret: false },
        { sku_id: 'sku-ms-3', name: 'Fridge Raid', image_url: figurePlaceholder('#dbe7f7', '#3b6fb6'), is_secret: false },
        { sku_id: 'sku-ms-4', name: 'Pillow Fort', image_url: figurePlaceholder('#f3d6e6', '#c04fa0'), is_secret: false },
        { sku_id: 'sku-ms-5', name: 'Night Owl', image_url: figurePlaceholder('#e8e1f5', '#6d5bd0'), is_secret: false },
        { sku_id: 'sku-ms-s', name: 'Golden Moon Snack', image_url: figurePlaceholder('#fff1b8', '#d4a106'), is_secret: true },
    ],
    [tinyGarden.id]: [
        { sku_id: 'sku-tg-1', name: 'Sprout', image_url: figurePlaceholder('#e3f3d6', '#5a9a2e'), is_secret: false },
        { sku_id: 'sku-tg-2', name: 'Watering Can', image_url: figurePlaceholder('#dbe7f7', '#3b6fb6'), is_secret: false },
        { sku_id: 'sku-tg-3', name: 'Snail Mail', image_url: figurePlaceholder('#ffe39c', '#f59e0b'), is_secret: false },
        { sku_id: 'sku-tg-s', name: 'Rainbow Bloom', image_url: figurePlaceholder('#ffd9e8', '#e0457b'), is_secret: true },
    ],
};

export const sampleLockLimits: PopNowLockLimits = { max_lock_seconds: 300 };

export const sampleLockAccount: LockAccount = { label: 'raziq@…', area: 'MY', session_valid: true };

// Chance-based rules can only chase non-secret figures (secrets have no odds).
const nightOwl = sampleFigures[midnightSnack.id][4];

export const sampleLockRules: AutoLockRule[] = [
    {
        id: 'rule-1',
        product: midnightSnack,
        target: {
            kind: 'specific',
            picks: [
                { figure: sampleFigures[midnightSnack.id][1], count: 2 },
                { figure: nightOwl, count: 1 },
            ],
            min_confirmations: 2,
            verified_only: false,
        },
        trigger: 'chance_reached',
        min_chance: 0.6,
        lock_duration_seconds: 300,
        renew: { renew_when_seconds_left: 30, max_total_hold_seconds: 900 },
        locks_made: 0,
        enabled: true,
        expires_at: null,
        created_at: daysAgo(2),
    },
    {
        id: 'rule-2',
        product: tinyGarden,
        target: { kind: 'random', count: 2 },
        trigger: 'sale_opens',
        min_chance: null,
        lock_duration_seconds: 180,
        renew: null,
        locks_made: 0,
        enabled: true,
        expires_at: null,
        created_at: hoursAgo(20),
    },
];

/**
 * Relative to page load so countdowns are live. The held box was first locked
 * 10 min ago and is on its 2nd renewal (5 min holds, re-locked with 30s left,
 * up to 15 min total).
 */
const heldSince = minutesAgo(10);
const heldAt = minutesAgo(1);
const heldUntil = new Date(new Date(heldAt).getTime() + 300_000).toISOString();
const holdEndsAt = new Date(new Date(heldSince).getTime() + 900_000).toISOString();

export const sampleLockAttempts: LockAttempt[] = [
    {
        id: 'att-4', rule_id: 'rule-1', product: midnightSnack, set_no: 'S-1042', box_no: '07', figure: nightOwl,
        status: 'locked', attempted_at: heldSince, locked_at: heldAt, lock_expires_at: heldUntil, renewals: 2, hold_ends_at: holdEndsAt, error: null,
    },
    {
        id: 'att-3', rule_id: 'rule-1', product: midnightSnack, set_no: 'S-1038', box_no: '03', figure: nightOwl,
        status: 'failed', attempted_at: hoursAgo(2), locked_at: null, lock_expires_at: null, renewals: 0, hold_ends_at: null, error: 'Box already locked by another shopper',
    },
    {
        id: 'att-2', rule_id: 'rule-1', product: midnightSnack, set_no: 'S-1031', box_no: '11', figure: nightOwl,
        status: 'expired', attempted_at: hoursAgo(9), locked_at: hoursAgo(9), lock_expires_at: hoursAgo(8.9), renewals: 3, hold_ends_at: hoursAgo(8.75), error: null,
    },
    {
        id: 'att-1', rule_id: 'rule-1', product: midnightSnack, set_no: 'S-1020', box_no: '05', figure: null,
        status: 'failed', attempted_at: daysAgo(1), locked_at: null, lock_expires_at: null, renewals: 0, hold_ends_at: null, error: 'Pop Mart session expired',
    },
];
