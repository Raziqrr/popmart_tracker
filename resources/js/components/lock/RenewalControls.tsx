import { RefreshCcw, TriangleAlert } from 'lucide-react';
import type { LockRenewal } from '@/types/lock';
import { formatHold, renewalPlan } from './lockText';

const RENEW_AT_OPTIONS = [10, 20, 30, 45, 60, 90];
const TOTAL_OPTIONS = [600, 900, 1200, 1800, 2700, 3600];

/** Sensible starting point: re-lock with 30s left (or a tenth of the hold), hold for 3 holds' worth. */
export function defaultRenewal(holdSeconds: number): LockRenewal {
    const renewAt = Math.min(30, Math.max(5, Math.floor(holdSeconds / 10)));
    const total = TOTAL_OPTIONS.find((t) => t >= holdSeconds * 3) ?? TOTAL_OPTIONS.at(-1)!;
    return { renew_when_seconds_left: renewAt, max_total_hold_seconds: total };
}

/**
 * Keeps a renewal valid when the hold time changes, snapping to options the
 * dropdowns offer: the largest re-lock point still below the hold, and a total
 * above the hold.
 */
export function fitRenewal(renew: LockRenewal, holdSeconds: number): LockRenewal {
    const renewAtChoices = RENEW_AT_OPTIONS.filter((s) => s < holdSeconds);
    const totalChoices = TOTAL_OPTIONS.filter((s) => s > holdSeconds);

    return {
        renew_when_seconds_left:
            renewAtChoices.filter((s) => s <= renew.renew_when_seconds_left).at(-1) ?? renewAtChoices[0] ?? Math.max(1, holdSeconds - 1),
        max_total_hold_seconds: totalChoices.find((s) => s >= renew.max_total_hold_seconds) ?? totalChoices.at(-1) ?? holdSeconds + 60,
    };
}

interface RenewalControlsProps {
    holdSeconds: number;
    value: LockRenewal | null;
    onChange: (renew: LockRenewal | null) => void;
}

/**
 * Auto-renew settings: when to re-lock and how long to keep holding in total,
 * with a timeline of when each re-lock happens.
 */
export function RenewalControls({ holdSeconds, value, onChange }: RenewalControlsProps) {
    const renewAtChoices = RENEW_AT_OPTIONS.filter((s) => s < holdSeconds);
    const totalChoices = TOTAL_OPTIONS.filter((s) => s > holdSeconds);

    return (
        <div className="flex flex-col gap-3 border border-black/10 p-3">
            <label className="flex cursor-pointer items-start justify-between gap-3">
                <span className="flex items-start gap-2">
                    <RefreshCcw aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                    <span className="flex flex-col">
                        <span className="text-sm font-bold">Keep holding (auto-renew)</span>
                        <span className="text-xs text-black/60">Lock the same box again just before each hold runs out.</span>
                    </span>
                </span>
                <button
                    type="button"
                    role="switch"
                    aria-checked={!!value}
                    aria-label="Auto-renew hold"
                    onClick={() => onChange(value ? null : defaultRenewal(holdSeconds))}
                    className="relative h-6 w-11 shrink-0 rounded-full bg-black/20 transition-colors aria-checked:bg-status-warning-ink"
                >
                    <span
                        aria-hidden="true"
                        className={`absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition-transform ${value ? 'translate-x-5' : ''}`}
                    />
                </button>
            </label>

            {value && (
                <>
                    <div className="flex flex-wrap gap-4 text-xs">
                        <label className="flex items-center gap-2 font-medium">
                            Re-lock when
                            <select
                                value={value.renew_when_seconds_left}
                                onChange={(e) => onChange({ ...value, renew_when_seconds_left: Number(e.target.value) })}
                                className="border border-black/20 bg-white px-1.5 py-1"
                            >
                                {renewAtChoices.map((s) => (
                                    <option key={s} value={s}>
                                        {formatHold(s)}
                                    </option>
                                ))}
                            </select>
                            left
                        </label>
                        <label className="flex items-center gap-2 font-medium">
                            Keep holding for up to
                            <select
                                value={value.max_total_hold_seconds}
                                onChange={(e) => onChange({ ...value, max_total_hold_seconds: Number(e.target.value) })}
                                className="border border-black/20 bg-white px-1.5 py-1"
                            >
                                {totalChoices.map((s) => (
                                    <option key={s} value={s}>
                                        {formatHold(s)}
                                    </option>
                                ))}
                            </select>
                            in total
                        </label>
                    </div>

                    <RenewalTimeline holdSeconds={holdSeconds} renew={value} />

                    <p className="flex items-start gap-1.5 text-[11px] text-status-warning-ink">
                        <TriangleAlert aria-hidden="true" className="mt-px size-3.5 shrink-0" />
                        Each re-lock re-selects the box, which resets Pop Mart's timer. If a re-select fails (network, expired session), the hold
                        ends early and you'll be alerted.
                    </p>
                </>
            )}
        </div>
    );
}

/** One bar per lock, offset by when it's taken, across the total hold window. */
export function RenewalTimeline({ holdSeconds, renew }: { holdSeconds: number; renew: LockRenewal }) {
    const { locks, step } = renewalPlan(holdSeconds, renew);
    const total = renew.max_total_hold_seconds;
    const pct = (s: number) => `${(Math.min(s, total) / total) * 100}%`;
    const clock = (s: number) => `${Math.floor(s / 60)}:${String(Math.round(s % 60)).padStart(2, '0')}`;

    return (
        <figure className="flex flex-col gap-1.5">
            <figcaption className="text-xs font-medium">
                {locks} lock{locks > 1 ? 's' : ''} per box: first at 0:00
                {locks > 1 &&
                    (locks <= 5
                        ? `, re-locks at ${Array.from({ length: locks - 1 }, (_, i) => clock((i + 1) * step)).join(', ')}`
                        : `, then re-locks every ${clock(step)}`)}
                ; released at {clock(total)}.
            </figcaption>
            <div role="img" aria-label={`${locks} overlapping holds covering ${formatHold(total)}`} className="relative h-3 bg-tile">
                {Array.from({ length: locks }, (_, i) => {
                    const start = i * step;
                    return (
                        <span
                            key={i}
                            className={`absolute h-full rounded-r-sm ${i % 2 ? 'top-0 bg-status-warning' : 'top-0 bg-status-warning-ink'}`}
                            style={{ left: pct(start), width: `calc(${pct(start + holdSeconds)} - ${pct(start)})`, opacity: 0.85 }}
                        />
                    );
                })}
            </div>
            <div className="flex justify-between text-[10px] text-black/50 tabular-nums">
                <span>0:00</span>
                <span>{clock(total)}</span>
            </div>
        </figure>
    );
}
