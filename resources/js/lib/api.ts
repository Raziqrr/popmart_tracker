import type { AuthState } from '@/lib/capabilities';
import type { LuckyPoints, TaskClaimResult } from '@/types/account';
import type { AlertInbox, AlertSettings } from '@/types/alerts';
import type { AutoLockRule, AutoLockRuleDraft, LockAttempt } from '@/types/lock';

/**
 * Error codes our API returns in { error: { code, message } }.
 * The popmart_* codes mirror App\Services\Api\PopMart\PopMartError;
 * unauthenticated, forbidden and popmart_not_connected come from
 * App\Auth\CapabilityException. validation_failed, page_expired, network and
 * http_error are added here on the client.
 */
export type ApiErrorCode =
    | 'popmart_session_expired'
    | 'popmart_rate_limited'
    | 'popmart_unreachable'
    | 'popmart_rejected'
    | 'popmart_unexpected_response'
    | 'popmart_throttled'
    | 'not_available'
    | 'action_in_progress'
    | 'unauthenticated'
    | 'forbidden'
    | 'popmart_not_connected'
    | 'validation_failed'
    | 'page_expired'
    | 'network'
    | 'http_error';

export class ApiError extends Error {
    constructor(
        readonly code: ApiErrorCode,
        message: string,
        readonly status: number | null = null,
        /** Seconds to wait before retrying, when the server says (rate limits). */
        readonly retryAfter: number | null = null,
        /** Per-field messages from a Laravel validation error. */
        readonly fieldErrors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

/** Laravel's XSRF-TOKEN cookie, sent back as X-XSRF-TOKEN on writes (account routes run on the web stack). */
function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * fetch() for our own JSON API. Resolves with the parsed body; rejects with an
 * ApiError for every failure (offline, not logged in, Pop Mart errors,
 * validation), so callers only ever handle one error type.
 */
export async function apiRequest<T>(path: string, init: { method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'; body?: unknown; headers?: Record<string, string> } = {}): Promise<T> {
    const method = init.method ?? 'GET';
    const token = method === 'GET' ? null : xsrfToken();
    let response: Response;

    try {
        response = await fetch(path, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(init.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
                ...init.headers,
            },
            body: init.body !== undefined ? JSON.stringify(init.body) : undefined,
        });
    } catch {
        throw new ApiError('network', 'Could not reach Popmart Tracker. Check your connection.');
    }

    const body = await response.json().catch(() => null);

    if (response.ok) {
        return body as T;
    }

    if (body?.error?.code) {
        throw new ApiError(body.error.code, body.error.message, response.status, body.error.retry_after ?? null);
    }

    if (response.status === 422 && body?.errors) {
        throw new ApiError('validation_failed', body.message ?? 'Some details were not accepted.', 422, null, body.errors);
    }

    if (response.status === 419) {
        throw new ApiError('page_expired', 'This page has been open too long. Reload it and try again.', 419);
    }

    throw new ApiError('http_error', body?.message ?? `Request failed (HTTP ${response.status}).`, response.status);
}

export type ApiErrorAction = 'login' | 'connect' | 'reconnect' | 'retry' | 'wait' | 'reload' | null;

export interface ApiErrorDescription {
    title: string;
    detail: string;
    /** What the user can do about it; drives the button ApiErrorNotice shows. */
    action: ApiErrorAction;
}

/** A user-facing title, detail and next step for any error a request can throw. */
export function describeApiError(error: unknown): ApiErrorDescription {
    if (!(error instanceof ApiError)) {
        return { title: 'Something went wrong', detail: error instanceof Error ? error.message : 'Please try again.', action: 'retry' };
    }

    switch (error.code) {
        case 'unauthenticated':
            return { title: 'Log in first', detail: error.message, action: 'login' };
        case 'popmart_not_connected':
            return { title: 'Pop Mart not connected', detail: error.message, action: 'connect' };
        case 'forbidden':
            return { title: 'Not your account', detail: error.message, action: null };
        case 'popmart_session_expired':
            return { title: 'Pop Mart session expired', detail: 'Log in on Pop Mart again and reconnect your account.', action: 'reconnect' };
        case 'popmart_rate_limited':
            return {
                title: 'Pop Mart is busy',
                detail: error.retryAfter ? `Too many requests. Try again in ${error.retryAfter}s.` : 'Too many requests. Wait a moment and try again.',
                action: 'wait',
            };
        case 'popmart_throttled':
            return {
                title: 'Busy right now',
                detail: error.retryAfter ? `Too many Pop Mart requests. Try again in ${error.retryAfter}s.` : error.message,
                action: 'wait',
            };
        case 'not_available':
            return { title: 'Not available yet', detail: error.message, action: null };
        case 'action_in_progress':
            return { title: 'Still working on it', detail: error.message, action: 'retry' };
        case 'popmart_unreachable':
        case 'network':
            return { title: "Couldn't connect", detail: error.message, action: 'retry' };
        case 'validation_failed':
            return { title: 'Check the details', detail: Object.values(error.fieldErrors)[0]?.[0] ?? error.message, action: null };
        case 'page_expired':
            return { title: 'Page expired', detail: error.message, action: 'reload' };
        default:
            return { title: 'Pop Mart refused the request', detail: error.message, action: 'retry' };
    }
}

/** GET /api/me: who's logged in, their Pop Mart connection, and what they can do. */
export function fetchMe(): Promise<AuthState> {
    return apiRequest('/api/me');
}

/** POST /api/popmart-accounts/connect with the session the extension captured ({ status: 'captured', cookie, expires_at }). */
export function connectPopMart(session: { cookie: string; expires_at: string | null }): Promise<{ connected: true; account_id: number; expires_at: string | null }> {
    return apiRequest('/api/popmart-accounts/connect', { method: 'POST', body: session });
}

/** POST /api/popmart-accounts/{id}/claim-tasks; also returns the refreshed lucky points when readable. */
export function claimTasks(accountId: number): Promise<{ results: TaskClaimResult[]; points: LuckyPoints | null }> {
    return apiRequest(`/api/popmart-accounts/${accountId}/claim-tasks`, { method: 'POST' });
}

/** GET /api/lucky-points: the latest synced balance, or null before the first sync. */
export function fetchLuckyPoints(): Promise<LuckyPoints | null> {
    return apiRequest('/api/lucky-points');
}

/**
 * POST /api/popmart-accounts/{id}/redeem-points. Pass the same key for a
 * retry so it can never redeem twice (e.g. crypto.randomUUID() per confirm).
 * Answers not_available until Pop Mart's redeem call is mapped.
 */
export function redeemLuckyPoints(accountId: number, idempotencyKey: string, points?: number): Promise<{ replayed: boolean }> {
    return apiRequest(`/api/popmart-accounts/${accountId}/redeem-points`, {
        method: 'POST',
        body: points ? { points } : {},
        headers: { 'Idempotency-Key': idempotencyKey },
    });
}

/** GET /api/alerts: the newest alerts and how many are unread. */
export function fetchAlerts(): Promise<AlertInbox> {
    return apiRequest('/api/alerts');
}

/** POST /api/alerts/read: mark these alerts read, or all of them without ids. */
export function markAlertsRead(ids?: string[]): Promise<{ unread: number }> {
    return apiRequest('/api/alerts/read', { method: 'POST', body: ids ? { ids } : {} });
}

/** GET /api/alert-settings */
export function fetchAlertSettings(): Promise<AlertSettings> {
    return apiRequest('/api/alert-settings');
}

/** PUT /api/alert-settings */
export function saveAlertSettings(settings: Pick<AlertSettings, 'types' | 'email' | 'telegram' | 'telegram_chat_id'>): Promise<AlertSettings> {
    return apiRequest('/api/alert-settings', { method: 'PUT', body: settings });
}

/** GET /api/auto-lock: the user's rules, recent lock attempts, and whether the server has auto-lock switched on. */
export function fetchAutoLock(): Promise<{ enabled_on_server: boolean; rules: AutoLockRule[]; attempts: LockAttempt[] }> {
    return apiRequest('/api/auto-lock');
}

/**
 * POST /api/auto-lock. `acknowledged` is the risk tick box; renew must stay
 * null (auto-renew isn't supported: Pop Mart doesn't reset a held box's timer).
 */
export function createAutoLockRule(productId: string, draft: AutoLockRuleDraft, acknowledged: boolean): Promise<AutoLockRule> {
    const { target } = draft;
    const picks =
        target.kind === 'specific'
            ? target.picks.map((p) => ({ sku_id: p.figure.sku_id, count: p.count }))
            : target.kind === 'ranked'
              ? target.picks.map((p) => ({ sku_id: p.figure.sku_id, priority: p.priority }))
              : undefined;

    return apiRequest('/api/auto-lock', {
        method: 'POST',
        body: {
            product_id: productId,
            target: {
                kind: target.kind,
                count: target.kind === 'specific' ? undefined : target.count,
                picks,
                min_confirmations: target.kind === 'random' ? undefined : target.min_confirmations,
                verified_only: target.kind === 'random' ? undefined : target.verified_only,
            },
            trigger: draft.trigger,
            min_chance: draft.min_chance,
            lock_duration_seconds: draft.lock_duration_seconds,
            checkout_immediately: draft.checkout_immediately,
            enabled: draft.enabled,
            expires_at: draft.expires_at,
            acknowledged,
        },
    });
}

/** PATCH /api/auto-lock/{id}: switch a rule on or off, or change when it ends. */
export function updateAutoLockRule(ruleId: string, changes: { enabled?: boolean; expires_at?: string | null }): Promise<AutoLockRule> {
    return apiRequest(`/api/auto-lock/${ruleId}`, { method: 'PATCH', body: changes });
}

/** DELETE /api/auto-lock/{id} */
export function deleteAutoLockRule(ruleId: string): Promise<{ deleted: true }> {
    return apiRequest(`/api/auto-lock/${ruleId}`, { method: 'DELETE' });
}
