import { Crown, ListOrdered, Shuffle, Target, X } from 'lucide-react';
import type { LockFigure, LockPick, LockRankedPick, LockTarget } from '@/types/lock';
import { totalBoxes } from './lockText';
import { RankedQueue } from './RankedQueue';
import { Stepper } from './Stepper';

interface LockPickerProps {
    value: LockTarget;
    onChange: (target: LockTarget) => void;
    figures: LockFigure[];
}

/**
 * Chooses what to lock. Random: the system takes any free boxes, you set how
 * many. Specific: click a figure to add one box of it, click again for
 * another; each picked figure gets its own − n + stepper. Ranked: set a
 * total box count and a priority order — fills from figure #1's hinted
 * boxes first, falling back down the list as needed to reach the total.
 */
export function LockPicker({ value, onChange, figures }: LockPickerProps) {
    const total = totalBoxes(value);

    // Keep hint settings when switching between hint-based modes.
    const hintSettings =
        value.kind === 'specific' || value.kind === 'ranked'
            ? { min_confirmations: value.min_confirmations, verified_only: value.verified_only }
            : { min_confirmations: 2, verified_only: false };

    const setMode = (kind: LockTarget['kind']) => {
        if (kind === value.kind) return;
        if (kind === 'random') {
            onChange({ kind: 'random', count: Math.max(1, total) });
        } else if (kind === 'specific') {
            onChange({ kind: 'specific', picks: [], ...hintSettings });
        } else {
            onChange({ kind: 'ranked', count: Math.max(1, total), picks: [], ...hintSettings });
        }
    };

    const picks = value.kind === 'specific' ? value.picks : [];
    const countOf = (figure: LockFigure) => picks.find((p) => p.figure.sku_id === figure.sku_id)?.count ?? 0;

    const setCount = (figure: LockFigure, count: number) => {
        if (value.kind !== 'specific') return;
        const others = value.picks.filter((p) => p.figure.sku_id !== figure.sku_id);
        const next: LockPick[] =
            count > 0
                ? // Keep figures in grid order so the summary reads predictably.
                  figures.flatMap((f) => (f.sku_id === figure.sku_id ? [{ figure, count }] : others.filter((p) => p.figure.sku_id === f.sku_id)))
                : others;
        onChange({ ...value, picks: next });
    };

    const rankedPicks = value.kind === 'ranked' ? [...value.picks].sort((a, b) => a.priority - b.priority) : [];
    const priorityOf = (figure: LockFigure) => rankedPicks.find((p) => p.figure.sku_id === figure.sku_id)?.priority ?? null;

    const toggleRanked = (figure: LockFigure) => {
        if (value.kind !== 'ranked') return;
        const existing = rankedPicks.find((p) => p.figure.sku_id === figure.sku_id);
        const next: LockRankedPick[] = existing
            ? rankedPicks.filter((p) => p.figure.sku_id !== figure.sku_id).map((p, i) => ({ ...p, priority: i + 1 }))
            : [...rankedPicks, { figure, priority: rankedPicks.length + 1 }];
        onChange({ ...value, picks: next });
    };

    return (
        <div className="flex flex-col gap-3">
            <div role="radiogroup" aria-label="Lock mode" className="grid grid-cols-3 border border-black">
                {(
                    [
                        { kind: 'random', title: 'Random', detail: 'Any free box', Icon: Shuffle },
                        { kind: 'specific', title: 'Specific', detail: 'Pick figures', Icon: Target },
                        { kind: 'ranked', title: 'Ranked', detail: 'Priority order', Icon: ListOrdered },
                    ] as const
                ).map(({ kind, title, detail, Icon }) => (
                    <button
                        key={kind}
                        type="button"
                        role="radio"
                        aria-checked={value.kind === kind}
                        onClick={() => setMode(kind)}
                        className="flex items-center justify-center gap-2 px-3 py-2.5 text-left aria-checked:bg-black aria-checked:text-white"
                    >
                        <Icon aria-hidden="true" className="size-4 shrink-0" />
                        <span className="flex flex-col leading-tight">
                            <span className="text-sm font-bold">{title}</span>
                            <span className="text-[11px] opacity-70">{detail}</span>
                        </span>
                    </button>
                ))}
            </div>

            {value.kind === 'random' ? (
                <div className="flex flex-wrap items-center justify-between gap-3 bg-tile p-3">
                    <div className="flex flex-col">
                        <span className="text-sm font-bold">How many boxes?</span>
                        <span className="text-[11px] text-black/60">The system locks whichever boxes are free first.</span>
                    </div>
                    <Stepper value={value.count} min={1} onChange={(count) => onChange({ kind: 'random', count })} label="random boxes" />
                </div>
            ) : value.kind === 'specific' ? (
                <div className="flex flex-col gap-2">
                    <p className="text-[11px] text-black/60">Click a figure to add a box of it; click again for another.</p>
                    <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {figures.map((figure) => (
                            <FigureOption
                                key={figure.sku_id}
                                figure={figure}
                                count={countOf(figure)}
                                onChange={(count) => setCount(figure, count)}
                            />
                        ))}
                    </ul>
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3 bg-tile p-3">
                        <div className="flex flex-col">
                            <span className="text-sm font-bold">How many boxes total?</span>
                            <span className="text-[11px] text-black/60">Fills from your #1 figure's hinted boxes first, falling back down the list.</span>
                        </div>
                        <Stepper value={value.count} min={1} onChange={(count) => onChange({ ...value, count })} label="boxes total" />
                    </div>
                    <p className="text-[11px] text-black/60">Click a figure to add it to the queue below; click a queued figure to remove it.</p>
                    <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {figures.map((figure) => (
                            <RankedFigureOption
                                key={figure.sku_id}
                                figure={figure}
                                priority={priorityOf(figure)}
                                onToggle={() => toggleRanked(figure)}
                            />
                        ))}
                    </ul>
                    <div className="flex flex-col gap-2 border-t border-black/10 pt-3">
                        <h4 className="text-xs font-bold tracking-wider uppercase">Priority queue</h4>
                        <RankedQueue
                            picks={rankedPicks}
                            onReorder={(next) => onChange({ ...value, picks: next })}
                            onRemove={(skuId) => onChange({ ...value, picks: rankedPicks.filter((p) => p.figure.sku_id !== skuId).map((p, i) => ({ ...p, priority: i + 1 })) })}
                        />
                    </div>
                </div>
            )}

            <div className="flex items-center justify-between gap-3">
                <p className="text-xs font-medium text-black/60" aria-live="polite">
                    {total === 1 ? '1 box' : `${total} boxes`} in total
                </p>
                {value.kind === 'specific' && value.picks.length > 0 && (
                    <button
                        type="button"
                        onClick={() => onChange({ ...value, picks: [] })}
                        className="inline-flex items-center gap-1 border border-black/20 px-2 py-1 text-xs font-bold hover:border-status-critical-ink hover:text-status-critical-ink"
                    >
                        <X aria-hidden="true" className="size-3.5" />
                        Clear all
                    </button>
                )}
                {value.kind === 'ranked' && value.picks.length > 0 && (
                    <button
                        type="button"
                        onClick={() => onChange({ ...value, picks: [] })}
                        className="inline-flex items-center gap-1 border border-black/20 px-2 py-1 text-xs font-bold hover:border-status-critical-ink hover:text-status-critical-ink"
                    >
                        <X aria-hidden="true" className="size-3.5" />
                        Clear all
                    </button>
                )}
            </div>
        </div>
    );
}

function RankedFigureOption({ figure, priority, onToggle }: { figure: LockFigure; priority: number | null; onToggle: () => void }) {
    const selected = priority !== null;

    return (
        <li className={`relative flex flex-col border transition-colors ${selected ? 'border-2 border-black bg-white' : 'border-black/10 bg-white hover:border-black'}`}>
            <button
                type="button"
                onClick={onToggle}
                aria-label={`${figure.name}${figure.is_secret ? ' (secret)' : ''}${selected ? `: priority ${priority}, click to remove` : ': click to add to the queue'}`}
                className="flex flex-col items-center gap-1 p-2 pt-3 text-center select-none"
            >
                {figure.is_secret && (
                    <span className="absolute top-1.5 left-1.5 inline-flex items-center gap-0.5 bg-black px-1 text-[9px] font-bold text-white uppercase">
                        <Crown aria-hidden="true" className="size-2.5" fill="currentColor" />
                        Secret
                    </span>
                )}
                {selected && (
                    <span
                        key={priority}
                        aria-hidden="true"
                        className="absolute top-1.5 right-1.5 grid size-6 place-items-center rounded-full bg-brand text-[11px] font-bold text-white tabular-nums motion-safe:animate-pop"
                    >
                        {priority}
                    </span>
                )}
                <span className="aspect-square w-3/4">{figure.image_url && <img src={figure.image_url} alt="" draggable={false} className="size-full object-contain" />}</span>
                <span className="line-clamp-2 text-xs leading-tight font-medium">{figure.name}</span>
            </button>
        </li>
    );
}

function FigureOption({ figure, count, onChange }: { figure: LockFigure; count: number; onChange: (count: number) => void }) {
    const selected = count > 0;
    const increment = () => onChange(count + 1);

    return (
        <li
            className={`relative flex flex-col border transition-colors ${
                selected ? 'border-2 border-black bg-white' : 'border-black/10 bg-white hover:border-black'
            }`}
        >
            <button
                type="button"
                onClick={increment}
                aria-label={`${figure.name}${figure.is_secret ? ' (secret)' : ''}: ${count} selected, add one`}
                className="flex flex-col items-center gap-1 p-2 pt-3 text-center select-none"
            >
                {figure.is_secret && (
                    <span className="absolute top-1.5 left-1.5 inline-flex items-center gap-0.5 bg-black px-1 text-[9px] font-bold text-white uppercase">
                        <Crown aria-hidden="true" className="size-2.5" fill="currentColor" />
                        Secret
                    </span>
                )}
                <span className="aspect-square w-3/4">{figure.image_url && <img src={figure.image_url} alt="" draggable={false} className="size-full object-contain" />}</span>
                <span className="flex items-center gap-1.5">
                    <span className="line-clamp-2 text-xs leading-tight font-medium">{figure.name}</span>
                    {selected && (
                        <span
                            // Re-keyed on every change so the count pops.
                            key={count}
                            aria-hidden="true"
                            className="shrink-0 bg-brand px-1.5 py-0.5 text-[11px] leading-none font-bold text-white tabular-nums motion-safe:animate-pop"
                        >
                            ×{count}
                        </span>
                    )}
                </span>
            </button>

            {/* Delete this figure from the rule, whatever its count. Sits outside the tile button. */}
            {selected && (
                <button
                    type="button"
                    onClick={() => onChange(0)}
                    aria-label={`Remove ${figure.name}`}
                    title={`Remove ${figure.name}`}
                    className="absolute top-1.5 right-1.5 grid size-7 place-items-center rounded-full bg-black text-white shadow hover:bg-status-critical focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                    <X aria-hidden="true" className="size-4" strokeWidth={2.5} />
                </button>
            )}

            {selected && (
                <div className="flex justify-center border-t border-black/10 p-1.5">
                    <Stepper value={count} onChange={onChange} label={`boxes of ${figure.name}`} size="sm" />
                </div>
            )}
        </li>
    );
}
