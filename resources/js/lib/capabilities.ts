import { createContext, createElement, useContext } from 'react';
import type { ReactNode } from 'react';

/**
 * Mirrors app/Auth/Capability.php: what each gated action needs. The server
 * enforces this on every route; the frontend only uses it to show "log in" /
 * "connect Pop Mart" prompts instead of buttons that would fail.
 * Keep both lists in sync when adding a capability.
 */
export type Access = 'guest' | 'member' | 'popmart';

export const CAPABILITIES = {
    connect_popmart: { requires: 'member', label: 'connect a Pop Mart account' },
    watchlist: { requires: 'member', label: 'use your watchlist' },
    report_hints: { requires: 'member', label: 'report tip-card hints' },
    claim_tasks: { requires: 'popmart', label: 'claim daily tasks' },
    redeem_points: { requires: 'popmart', label: 'redeem lucky points' },
    lock_boxes: { requires: 'popmart', label: 'lock boxes' },
    auto_lock: { requires: 'popmart', label: 'set up auto-lock' },
    checkout: { requires: 'popmart', label: 'check out' },
} as const satisfies Record<string, { requires: Access; label: string }>;

export type Capability = keyof typeof CAPABILITIES;

/** GET /api/me (App\Http\Controllers\MeController). */
export interface AuthState {
    user: { id: number; name: string; email: string } | null;
    /** The connected Pop Mart account, if any; session_valid false means it needs reconnecting. */
    popmart: { account_id: number; session_valid: boolean; session_expires_at: string | null } | null;
    /** Capabilities the server says this user has right now. */
    capabilities: Capability[];
}

/** Why a capability is missing, i.e. which prompt to show. */
export type MissingReason = 'login' | 'connect' | 'reconnect';

export function checkCapability(auth: AuthState | null, capability: Capability): { allowed: boolean; reason: MissingReason | null } {
    // No auth state (UI lab, or /me not loaded yet): don't block anything; the server still decides.
    if (!auth || auth.capabilities.includes(capability)) return { allowed: true, reason: null };

    if (!auth.user) return { allowed: false, reason: 'login' };
    if (CAPABILITIES[capability].requires === 'popmart') {
        return { allowed: false, reason: auth.popmart && !auth.popmart.session_valid ? 'reconnect' : 'connect' };
    }
    return { allowed: false, reason: 'login' };
}

const AuthContext = createContext<AuthState | null>(null);

/** Provide /api/me's result to everything below. Without it, every capability shows as allowed. */
export function AuthProvider({ value, children }: { value: AuthState | null; children: ReactNode }) {
    return createElement(AuthContext.Provider, { value }, children);
}

export function useAuth(): AuthState | null {
    return useContext(AuthContext);
}

/** const { allowed, reason } = useCan('claim_tasks') */
export function useCan(capability: Capability) {
    return checkCapability(useAuth(), capability);
}
