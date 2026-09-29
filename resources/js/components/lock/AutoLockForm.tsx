import { CircleUserRound, ShieldAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import type { AutoLockRuleDraft, LockAccount, LockFigure, LockTrigger, PopNowLockLimits } from '@/types/lock';
import { describeRule, formatHold, totalBoxes, triggerLabels, FIGURE_TRIGGERS, isFigureTrigger, DEFAULT_MIN_CHANCE } from './lockText';
import { LockPicker } from './LockPicker';
import { fitRenewal, RenewalControls } from './RenewalControls';

const HOLD_OPTIONS = [60, 120, 180, 300, 600];
const HOUR = 3_600_000;
/** Thresholds offered for the 'chance_reached' trigger. */
export const CHANCE_OPTIONS = [0.5, 0.6, 0.75, 0.9];

/** Why saving is blocked, or null when the account can lock. */
export function accountBlocker(account: LockAccount | null): string | null {
    if (!account) return 'Link your Pop Mart account first.';
    if (!account.session_valid) return 'Your Pop Mart session expired. Reconnect to use auto-lock.';
    return null;
}

/** Whether a draft is complete enough to save (ignoring account and acknowledgement). */
export function draftIsValid(draft: AutoLockRuleDraft, limits: PopNowLockLimits): boolean {
    const total = totalBoxes(draft.target);
    if (total < 1) return false;
    // A renewal must fire before the hold ends and aim past a single hold.
    if (draft.renew && (draft.renew.renew_when_seconds_left >= draft.lock_duration_seconds || draft.renew.max_total_hold_seconds <= draft.lock_duration_seconds)) {
        return false;
    }
    return !(draft.target.kind === 'random' && isFigureTrigger(draft.trigger));
}

interface AutoLockFormProps {
    draft: AutoLockRuleDraft;
    onChange: (draft: AutoLockRuleDraft) => void;
    figures: LockFigure[];
    limits: PopNowLockLimits;
}

/**
 * Every auto-lock setting: what (random/specific picker plus hint trust),
 * when, hold time and when the rule ends. The full-page setup; the dialog
 * only shows the picker and uses defaults for the rest.
 */
export function AutoLockForm({ draft, onChange, figures, limits }: AutoLockFormProps) {
    const set = (patch: Partial<AutoLockRuleDraft>) => onChange({ ...draft, ...patch });
    // Specific and ranked both target figures, so both need the exclusion-trust
    // controls below and can use the chance-based triggers.
    const hintBased = draft.target.kind === 'specific' || draft.target.kind === 'ranked' ? draft.target : null;
    const triggers: LockTrigger[] = hintBased ? ['sale_opens', 'restock', ...FIGURE_TRIGGERS] : ['sale_opens', 'restock'];
    const holdChoices = HOLD_OPTIONS.filter((s) => s < limits.max_lock_seconds).concat(limits.max_lock_seconds);

    const expiry = draft.expires_at === null ? 'draw_end' : new Date(draft.expires_at).getTime() - Date.now() > 2 * 24 * HOUR ? '7d' : '24h';

    return (
        <div className="flex flex-col gap-8">
            <Section title="1. What to lock">
                <LockPicker
                    value={draft.target}
                    figures={figures}
                    onChange={(target) =>
                        // Chance triggers need chosen figures; fall back when switching to random.
                        set({ target, trigger: target.kind === 'random' && isFigureTrigger(draft.trigger) ? 'restock' : draft.trigger })
                    }
                />
                {hintBased && (
                    <div className="flex flex-wrap items-center gap-4 bg-tile p-3 text-xs">
                        <label className="flex items-center gap-2 font-medium">
                            Count user-reported exclusions with at least
                            <select
                                value={hintBased.min_confirmations}
                                onChange={(e) => set({ target: { ...hintBased, min_confirmations: Number(e.target.value) } })}
                                className="border border-black/20 bg-white px-1.5 py-1"
                            >
                                {[1, 2, 3, 5].map((n) => (
                                    <option key={n} value={n}>
                                        {n} confirmation{n > 1 ? 's' : ''}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex items-center gap-1.5 font-medium">
                            <input
                                type="checkbox"
                                checked={hintBased.verified_only}
                                onChange={(e) => set({ target: { ...hintBased, verified_only: e.target.checked } })}
                                className="accent-black"
                            />
                            Verified exclusions only
                        </label>
                    </div>
                )}
            </Section>

            <Section title="2. When">
                <div className="grid gap-2">
                    {triggers.map((t) => (
                        <label
                            key={t}
                            className="flex cursor-pointer items-start gap-2 border border-black/20 p-3 hover:border-black has-checked:border-2 has-checked:border-black"
                        >
                            <input type="radio" name="trigger" checked={draft.trigger === t} onChange={() => set({ trigger: t })} className="mt-0.5 accent-black" />
                            <span className="flex flex-col gap-2">
                                <span className="text-sm font-bold">{triggerLabels[t].title}</span>
                                <span className="text-xs text-black/60">{triggerLabels[t].detail}</span>
                                {t === 'chance_reached' && draft.trigger === 'chance_reached' && (
                                    <span className="flex items-center gap-2 text-xs font-medium">
                                        Fire at
                                        <select
                                            aria-label="Chance threshold"
                                            value={draft.min_chance ?? DEFAULT_MIN_CHANCE}
                                            onChange={(e) => set({ min_chance: Number(e.target.value) })}
                                            className="border border-black/20 bg-white px-1.5 py-1"
                                        >
                                            {CHANCE_OPTIONS.map((c) => (
                                                <option key={c} value={c}>
                                                    {Math.round(c * 100)}%
                                                </option>
                                            ))}
                                        </select>
                                        or higher
                                    </span>
                                )}
                            </span>
                        </label>
                    ))}
                </div>
            </Section>

            <Section title="3. Hold each box for">
                <div role="radiogroup" aria-label="Hold time" className="flex flex-wrap gap-2">
                    {holdChoices.map((s) => (
                        <button
                            key={s}
                            type="button"
                            role="radio"
                            aria-checked={draft.lock_duration_seconds === s}
                            onClick={() => set({ lock_duration_seconds: s, renew: draft.renew && fitRenewal(draft.renew, s) })}
                            className="border border-black/20 px-3 py-1.5 text-xs font-bold tabular-nums hover:border-black aria-checked:border-black aria-checked:bg-black aria-checked:text-white"
                        >
                            {formatHold(s)}
                            {s === limits.max_lock_seconds && <span className="ml-1 font-medium opacity-70">(max)</span>}
                        </button>
                    ))}
                </div>
                <p className="text-[11px] text-black/50">
                    Pop Mart holds a locked box for at most {formatHold(limits.max_lock_seconds)}. You'll get an alert to pay before it runs out.
                </p>
                <RenewalControls holdSeconds={draft.lock_duration_seconds} value={draft.renew} onChange={(renew) => set({ renew })} />
            </Section>

            <Section title="4. Rule ends">
                <select
                    value={expiry}
                    onChange={(e) =>
                        set({
                            expires_at:
                                e.target.value === 'draw_end'
                                    ? null
                                    : new Date(Date.now() + (e.target.value === '24h' ? 24 : 24 * 7) * HOUR).toISOString(),
                        })
                    }
                    className="w-fit border border-black/20 bg-white px-2 py-1.5 text-xs"
                >
                    <option value="draw_end">When the draw ends</option>
                    <option value="24h">In 24 hours</option>
                    <option value="7d">In 7 days</option>
                </select>
            </Section>
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <fieldset className="flex flex-col gap-3">
            <legend className="mb-3 text-xs font-bold tracking-wider uppercase">{title}</legend>
            {children}
        </fieldset>
    );
}

/** The linked account the lock acts as, or why it can't. */
export function AccountNotice({ account }: { account: LockAccount | null }) {
    const blocked = accountBlocker(account);

    return (
        <div className={`flex items-center gap-2 p-3 text-xs ${blocked ? 'bg-status-critical-tint text-status-critical-ink' : 'bg-tile'}`}>
            <CircleUserRound aria-hidden="true" className="size-4 shrink-0" />
            {blocked ?? (
                <span>
                    Locks as <strong>{account!.label}</strong> on Pop Mart {account!.area}.
                </span>
            )}
        </div>
    );
}

/** Live plain-sentence summary of the rule being set up. */
export function RuleSummary({ draft }: { draft: AutoLockRuleDraft }) {
    return (
        <p className="border-l-4 border-black bg-tile px-3 py-2 text-sm font-medium" aria-live="polite">
            {totalBoxes(draft.target) === 0 ? 'Pick at least one figure.' : describeRule(draft)}
        </p>
    );
}

/** Required tick box: auto-lock acts on the user's real Pop Mart account. */
export function RiskAcknowledgement({ checked, onChange }: { checked: boolean; onChange: (checked: boolean) => void }) {
    return (
        <label className="flex items-start gap-2 border border-status-warning-ink/40 bg-status-warning-tint p-3 text-xs text-status-warning-ink">
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="mt-0.5 accent-black" />
            <span className="flex gap-1.5">
                <ShieldAlert aria-hidden="true" className="size-4 shrink-0" />
                <span>
                    I understand this locks boxes on my real Pop Mart account automatically, holds them from other shoppers, and may break Pop
                    Mart's terms.
                </span>
            </span>
        </label>
    );
}
