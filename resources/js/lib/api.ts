import type { AuthState } from '@/lib/capabilities';
import type { TaskClaimResult } from '@/types/account';

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
export async function apiRequest<T>(path: string, init: { method?: 'GET' | 'POST'; body?: unknown } = {}): Promise<T> {
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

/** POST /api/popmart-accounts/{id}/claim-tasks */
export function claimTasks(accountId: number): Promise<{ results: TaskClaimResult[] }> {
    return apiRequest(`/api/popmart-accounts/${accountId}/claim-tasks`, { method: 'POST' });
}
