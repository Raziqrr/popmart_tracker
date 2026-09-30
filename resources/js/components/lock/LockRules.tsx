import { CalendarClock, Crown, Percent, Pencil, RefreshCw, Target } from 'lucide-react';
import { formatTimeAgo } from '@/lib/format';
import type { AutoLockRule, LockAttempt, LockTrigger } from '@/types/lock';
import { attemptStatusMeta, describeRule, totalBoxes } from './lockText';

const triggerIcons: Record<LockTrigger, typeof CalendarClock> = {
    sale_opens: CalendarClock,
    restock: RefreshCw,
    chance_reached: Percent,
    narrowed: Target,
};

interface LockRulesProps {
    rules: AutoLockRule[];
    attempts: LockAttempt[];
    onToggle?: (rule: AutoLockRule) => void;
    onEdit?: (rule: AutoLockRule) => void;
}

/** The user's auto-lock rules: plain-sentence summary, progress, last attempt, on/off switch. */
export function LockRules({ rules, attempts, onToggle, onEdit }: LockRulesProps) {
    if (rules.length === 0) {
        return (
            <p className="border border-dashed border-black/20 py-8 text-center text-sm text-black/50">
                No auto-lock rules. Use the lock icon on a POP NOW product to add one.
            </p>
        );
    }

    return (
        <ul className="divide-y divide-black/10 border-y border-black/10">
            {rules.map((rule) => {
                const Icon = triggerIcons[rule.trigger];
                const last = attempts.filter((a) => a.rule_id === rule.id).sort((a, b) => b.attempted_at.localeCompare(a.attempted_at))[0];
                const total = totalBoxes(rule.target);
                const done = rule.locks_made >= total;
                const hasSecret = rule.target.kind === 'specific' && rule.target.picks.some((p) => p.figure.is_secret);

                return (
                    <li key={rule.id} className={`flex items-center gap-3 py-3 ${rule.enabled ? '' : 'opacity-60'}`}>
                        <div className="size-12 shrink-0 bg-tile">
                            {rule.product.image_url && <img src={rule.product.image_url} alt="" className="size-full object-contain p-1" />}
                        </div>
                        <div className="min-w-0 grow">
                            <p className="line-clamp-1 text-xs font-bold">{rule.product.name}</p>
                            <p className="flex items-start gap-1.5 text-xs text-black/70">
                                <Icon aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
                                <span>
                                    {describeRule(rule)}
                                    {hasSecret && (
                                        <Crown aria-label="secret figure" className="ml-1 inline size-3 align-[-1px]" fill="currentColor" />
                                    )}
                                </span>
                            </p>
                            <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-black/50">
                                <span className="font-bold text-black tabular-nums">
                                    {rule.locks_made}/{total} locked
                                </span>
                                {last && (
                                    <span className="inline-flex items-center gap-1">
                                        Last:
                                        <span className={`px-1 font-bold ${attemptStatusMeta[last.status].tone}`}>{attemptStatusMeta[last.status].label}</span>
                                        {formatTimeAgo(last.attempted_at)}
                                    </span>
                                )}
                                {done && <span className="font-bold text-status-good-ink">Limit reached</span>}
                            </p>
                        </div>
                        {onEdit && (
                            <button
                                type="button"
                                onClick={() => onEdit(rule)}
                                aria-label={`Edit auto-lock for ${rule.product.name}`}
                                className="grid size-8 shrink-0 place-items-center border border-black/20 hover:border-black"
                            >
                                <Pencil aria-hidden="true" className="size-4" />
                            </button>
                        )}
                        {onToggle && (
                            <button
                                type="button"
                                role="switch"
                                aria-checked={rule.enabled}
                                aria-label={`Auto-lock for ${rule.product.name}`}
                                onClick={() => onToggle(rule)}
                                className="relative h-6 w-11 shrink-0 rounded-full bg-black/20 transition-colors aria-checked:bg-status-warning-ink"
                            >
                                <span
                                    aria-hidden="true"
                                    className={`absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition-transform ${rule.enabled ? 'translate-x-5' : ''}`}
                                />
                            </button>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
