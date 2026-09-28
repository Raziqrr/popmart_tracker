import { Crown, Lock } from 'lucide-react';
import { formatTimeAgo } from '@/lib/format';
import { boxCounts, setOdds } from '@/lib/popnow';
import type { PopNowBox, PopNowSet } from '@/types/popnow';

interface SetListProps {
    sets: { set: PopNowSet; boxes: PopNowBox[] }[];
    selectedId: number | null;
    onSelect: (set: PopNowSet) => void;
    /** Lock every free box in this one set to you. Omit to hide the per-set lock button. */
    onLockSet?: (set: PopNowSet, boxes: PopNowBox[]) => void;
}

/** Sets of one POP NOW product as selectable cards: boxes free/locked/sold and whether a secret may remain. */
export function SetList({ sets, selectedId, onSelect, onLockSet }: SetListProps) {
    return (
        <ul className="flex gap-2 overflow-x-auto pb-1" aria-label="Sets">
            {sets.map(({ set, boxes }) => {
                const counts = boxCounts(boxes);
                const secretLeft = setOdds(set, boxes).figures.some((f) => f.sku.is_secret && !f.revealed);
                const selected = set.id === selectedId;

                return (
                    <li key={set.id} className="relative shrink-0">
                        <button
                            type="button"
                            onClick={() => onSelect(set)}
                            aria-pressed={selected}
                            className="flex w-48 flex-col gap-2 border border-black/15 p-3 text-left hover:border-black aria-pressed:border-2 aria-pressed:border-black"
                        >
                            <span className="flex items-center justify-between gap-2">
                                <span className="text-sm font-bold">Set {set.set_no}</span>
                                {secretLeft && (
                                    <span className="inline-flex items-center gap-0.5 bg-black px-1 text-[9px] font-bold text-white uppercase">
                                        <Crown aria-hidden="true" className="size-2.5" fill="currentColor" />
                                        Secret in
                                    </span>
                                )}
                            </span>
                            {/* Part-to-whole: free / locked / sold, labelled below. */}
                            <span className="flex h-1.5 gap-0.5" aria-hidden="true">
                                <span className="bg-status-good" style={{ flexGrow: counts.available }} />
                                <span className="bg-status-warning" style={{ flexGrow: counts.locked_other + counts.locked_mine }} />
                                <span className="bg-black/20" style={{ flexGrow: counts.sold }} />
                            </span>
                            <span className="text-[11px] text-black/70 tabular-nums">
                                <strong>{counts.available}</strong> free · {counts.locked_other + counts.locked_mine} locked · {counts.sold} sold
                            </span>
                            <span className="text-[10px] text-black/50">checked {formatTimeAgo(set.last_seen_at)}</span>
                        </button>

                        {onLockSet && counts.available > 0 && (
                            <button
                                type="button"
                                onClick={() => onLockSet(set, boxes)}
                                aria-label={`Lock all ${counts.available} free boxes in set ${set.set_no} to you`}
                                title="Lock every free box in this set to you"
                                className="absolute -top-2 -right-2 inline-flex items-center gap-0.5 border border-black bg-status-warning-tint px-1.5 py-1 text-[10px] font-bold text-status-warning-ink shadow hover:bg-status-warning"
                            >
                                <Lock aria-hidden="true" className="size-3" />
                                {counts.available}
                            </button>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
