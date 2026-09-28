import { Crown, ExternalLink, LockOpen, RefreshCcw, TimerReset } from 'lucide-react';
import { formatDuration } from '@/lib/format';
import { useNow } from '@/lib/useNow';
import type { LockAttempt } from '@/types/lock';

interface ActiveLocksProps {
    locks: LockAttempt[];
    /** Where to pay: the box's checkout on Pop Mart. */
    payHref?: (lock: LockAttempt) => string;
    onRelease?: (lock: LockAttempt) => void;
}

/**
 * Boxes held for the user right now, most urgent first, each with a live
 * countdown bar to when Pop Mart releases it.
 */
export function ActiveLocks({ locks, payHref, onRelease }: ActiveLocksProps) {
    const now = useNow(1000);
    const held = locks
        .filter((l) => l.status === 'locked' && l.lock_expires_at)
        .sort((a, b) => a.lock_expires_at!.localeCompare(b.lock_expires_at!));

    if (held.length === 0) {
        return <p className="border border-dashed border-black/20 py-8 text-center text-sm text-black/50">No boxes held right now.</p>;
    }

    return (
        <ul className="grid gap-3 sm:grid-cols-2">
            {held.map((lock) => {
                const start = new Date(lock.locked_at!).getTime();
                const end = new Date(lock.lock_expires_at!).getTime();
                const left = Math.max(0, end - now);
                const share = Math.max(0, Math.min(1, left / (end - start)));

                // Auto-renew: the current hold is re-locked before it ends, until hold_ends_at.
                const firstLocked = new Date(lock.attempted_at).getTime();
                const holdEnd = lock.hold_ends_at ? new Date(lock.hold_ends_at).getTime() : null;
                const willRenew = holdEnd !== null && holdEnd > end;
                const totalLeft = holdEnd !== null ? Math.max(0, holdEnd - now) : left;
                const totalShare = holdEnd !== null ? Math.max(0, Math.min(1, totalLeft / (holdEnd - firstLocked))) : share;
                // Only urgent when the box is really about to be released.
                const urgent = totalLeft < 60_000;

                return (
                    <li key={lock.id} className="flex flex-col gap-3 border-2 border-status-warning-ink/50 bg-status-warning-tint/40 p-3">
                        <div className="flex items-start gap-3">
                            <div className="size-14 shrink-0 bg-white">
                                {(lock.figure?.image_url ?? lock.product.image_url) && (
                                    <img src={lock.figure?.image_url ?? lock.product.image_url!} alt="" className="size-full object-contain p-1" />
                                )}
                            </div>
                            <div className="min-w-0 grow">
                                <p className="line-clamp-1 text-xs font-bold">{lock.product.name}</p>
                                <p className="text-[11px] text-black/60">
                                    Set {lock.set_no} · Box {lock.box_no}
                                </p>
                                {lock.figure && (
                                    <p className="mt-0.5 inline-flex items-center gap-1 text-[11px] font-medium">
                                        {lock.figure.is_secret && <Crown aria-hidden="true" className="size-3" fill="currentColor" />}
                                        Hinted: {lock.figure.name}
                                    </p>
                                )}
                            </div>
                            <p
                                className={`flex shrink-0 flex-col items-end text-lg leading-tight font-bold tabular-nums ${urgent ? 'text-status-critical-ink' : 'text-black'}`}
                                aria-live={urgent ? 'polite' : 'off'}
                            >
                                <span className="flex items-center gap-1">
                                    <TimerReset aria-hidden="true" className="size-4" />
                                    {left > 0 ? countdown(left) : 'Released'}
                                </span>
                                {willRenew && left > 0 && <span className="text-[10px] font-medium text-black/60">then re-locks</span>}
                            </p>
                        </div>

                        <div
                            role="progressbar"
                            aria-label="Time left on the hold"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={Math.round(share * 100)}
                            className="h-1.5 bg-white"
                        >
                            <div
                                className={`h-full rounded-r-sm transition-[width] duration-1000 ease-linear ${urgent ? 'bg-status-critical' : 'bg-status-warning'}`}
                                style={{ width: `${share * 100}%` }}
                            />
                        </div>

                        {holdEnd !== null && (
                            <div className="flex flex-col gap-1">
                                <p className="flex flex-wrap items-center gap-x-2 text-[11px]">
                                    <span className="inline-flex items-center gap-1 font-bold text-status-warning-ink">
                                        <RefreshCcw aria-hidden="true" className="size-3.5" />
                                        Auto-renew
                                    </span>
                                    <span className="text-black/70">
                                        re-locked {lock.renewals}× · total hold ends in <strong className="tabular-nums">{countdown(totalLeft)}</strong>
                                    </span>
                                </p>
                                <div
                                    role="progressbar"
                                    aria-label="Time left on the total hold"
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={Math.round(totalShare * 100)}
                                    className="h-1 bg-white"
                                >
                                    <div
                                        className="h-full rounded-r-sm bg-status-warning-ink transition-[width] duration-1000 ease-linear"
                                        style={{ width: `${totalShare * 100}%` }}
                                    />
                                </div>
                            </div>
                        )}

                        <div className="flex flex-wrap items-center gap-2">
                            <a
                                href={payHref?.(lock) ?? '#'}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1.5 bg-brand px-3 py-1.5 text-xs font-bold tracking-wide text-white uppercase hover:bg-brand/85"
                            >
                                Pay on Pop Mart
                                <ExternalLink aria-hidden="true" className="size-3.5" />
                            </a>
                            {onRelease && (
                                <button
                                    type="button"
                                    onClick={() => onRelease(lock)}
                                    className="inline-flex items-center gap-1.5 border border-black/20 bg-white px-3 py-1.5 text-xs font-bold hover:border-black"
                                >
                                    <LockOpen aria-hidden="true" className="size-3.5" />
                                    Release
                                </button>
                            )}
                            <span className="ml-auto text-[11px] text-black/50">held {formatDuration(now - (holdEnd !== null ? firstLocked : start))}</span>
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

/** m:ss while under an hour. */
function countdown(ms: number): string {
    const total = Math.ceil(ms / 1000);
    const minutes = Math.floor(total / 60);
    return `${minutes}:${String(total % 60).padStart(2, '0')}`;
}
