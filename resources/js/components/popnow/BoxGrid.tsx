import { BadgeCheck, Lock, PackageOpen, Target, XCircle } from 'lucide-react';
import { formatPercent, hintStrength } from '@/lib/popnow';
import { useNow } from '@/lib/useNow';
import type { BoxState, PopNowBox, PopNowSet } from '@/types/popnow';

export const boxStateMeta: Record<BoxState, { label: string; tile: string }> = {
    available: { label: 'Free', tile: 'bg-white border-black/15 hover:border-black' },
    locked_other: {
        label: 'Locked by someone',
        // Diagonal hatching reads as "unavailable" without relying on colour.
        tile: 'border-black/10 bg-[repeating-linear-gradient(135deg,#ececec_0_6px,#f7f7f7_6px_12px)] text-black/50',
    },
    locked_mine: { label: 'Held for you', tile: 'border-status-warning-ink bg-status-warning-tint ring-2 ring-status-warning-ink ring-inset' },
    sold: { label: 'Sold', tile: 'border-black/5 bg-tile text-black/40' },
};

export function countdown(ms: number): string {
    const total = Math.max(0, Math.ceil(ms / 1000));
    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

interface BoxGridProps {
    set: PopNowSet;
    boxes: PopNowBox[];
    selectedId?: number | null;
    onSelect?: (box: PopNowBox) => void;
    /** Lock a free box straight away — via double-click on the tile, or its own Lock button below. Omit to hide both. */
    onLockBox?: (box: PopNowBox) => void;
    /** Boxes tied for the best shot at whatever figure is currently selected in SetChances. */
    highlightedBoxIds?: Set<number>;
    /** That figure's probability in the highlighted box(es), for the badge. */
    highlightProbability?: number;
}

/**
 * The set as Pop Mart lays it out (width × height). Each box shows its state,
 * how many figures its own hints have ruled out (never which figure it IS —
 * a tip-card hint only ever proves an exclusion), live lock countdowns, and
 * the revealed figure once sold. Boxes in `highlightedBoxIds` get a target
 * ring — set by clicking a figure in SetChances. Double-click a free box, or
 * its Lock button below, to lock it immediately (no confirm step — unlike
 * BoxDetail's single lock button, both of these are already a deliberate,
 * distinct gesture from the plain single-click that just selects a box).
 */
export function BoxGrid({ set, boxes, selectedId, onSelect, onLockBox, highlightedBoxIds, highlightProbability }: BoxGridProps) {
    const now = useNow(1000);
    const ordered = [...boxes].sort((a, b) => a.position - b.position);

    return (
        <ol
            aria-label={`Set ${set.set_no}, ${set.total_boxes} boxes`}
            className="grid gap-2"
            style={{ gridTemplateColumns: `repeat(${set.width}, minmax(0, 1fr))` }}
        >
            {ordered.map((box) => {
                // The best (most trusted) exclusion, just for its trust badge —
                // its sku is never shown as "the figure in the box".
                const bestExclusion = [...box.hints].sort(
                    (a, b) => Number(b.source === 'verified_api') - Number(a.source === 'verified_api') || b.confirmations - a.confirmations,
                )[0];
                const strength = bestExclusion && hintStrength(bestExclusion);
                const figure = box.reveal?.sku ?? null;
                const left = box.lock_expires_at ? new Date(box.lock_expires_at).getTime() - now : 0;
                const selected = box.id === selectedId;
                const highlighted = highlightedBoxIds?.has(box.id) ?? false;

                const label = [
                    `Box ${box.box_no}`,
                    boxStateMeta[box.state].label,
                    box.reveal
                        ? `revealed ${box.reveal.sku.name}`
                        : box.hints.length > 0
                          ? `${box.hints.length} figure${box.hints.length > 1 ? 's' : ''} ruled out`
                          : null,
                    highlighted && highlightProbability !== undefined ? `${formatPercent(highlightProbability)} chance for the selected figure` : null,
                    left > 0 ? `${countdown(left)} left` : null,
                ]
                    .filter(Boolean)
                    .join(', ');

                const canLock = box.state === 'available' && onLockBox;

                return (
                    <li key={box.id} className="flex flex-col gap-1">
                        <button
                            type="button"
                            onClick={() => onSelect?.(box)}
                            onDoubleClick={() => canLock && onLockBox(box)}
                            aria-pressed={selected}
                            aria-label={canLock ? `${label}, double-click to lock` : label}
                            title={canLock ? `${label} — double-click to lock` : label}
                            className={`relative flex aspect-square w-full flex-col items-center justify-center border p-1 transition-colors select-none ${
                                boxStateMeta[box.state].tile
                            } ${selected ? 'outline-3 outline-offset-2 outline-black' : ''} ${
                                highlighted ? 'ring-3 ring-brand ring-offset-1' : ''
                            }`}
                        >
                            <span className="absolute top-1 left-1.5 text-[10px] font-bold tabular-nums">{box.box_no}</span>

                            {highlighted && highlightProbability !== undefined && (
                                <span className="absolute -top-2 -right-2 z-10 inline-flex items-center gap-0.5 rounded-full bg-brand px-1.5 py-0.5 text-[9px] font-bold text-white shadow">
                                    <Target aria-hidden="true" className="size-2.5" />
                                    {formatPercent(highlightProbability)}
                                </span>
                            )}

                            {/* Only a real reveal ever shows a figure image — an exclusion hint never does. */}
                            {figure?.image_url && (
                                <img
                                    src={figure.image_url}
                                    alt=""
                                    draggable={false}
                                    className={`size-3/5 object-contain ${box.state === 'sold' ? 'opacity-40 grayscale' : ''}`}
                                />
                            )}

                            {/* Ruled-out count, top right — trust badge reflects the best exclusion on this box. */}
                            {box.hints.length > 0 && !box.reveal && (
                                <span
                                    className={`absolute top-1 right-1 inline-flex items-center gap-0.5 px-1 text-[9px] leading-4 font-bold ${
                                        strength === 'verified'
                                            ? 'bg-status-good-tint text-status-good-ink'
                                            : strength === 'strong'
                                              ? 'bg-black text-white'
                                              : 'bg-white text-black/60 ring-1 ring-black/15'
                                    }`}
                                >
                                    {strength === 'verified' ? <BadgeCheck aria-hidden="true" className="size-3" /> : <XCircle aria-hidden="true" className="size-3" />}
                                    {box.hints.length}
                                </span>
                            )}

                            {(box.state === 'locked_other' || box.state === 'locked_mine') && left > 0 && (
                                <span
                                    className={`absolute inset-x-1 bottom-1 inline-flex items-center justify-center gap-0.5 text-[10px] font-bold tabular-nums ${
                                        box.state === 'locked_mine' ? 'text-status-warning-ink' : 'text-black/60'
                                    }`}
                                >
                                    <Lock aria-hidden="true" className="size-3" />
                                    {box.state === 'locked_mine' ? 'Yours ' : ''}
                                    {countdown(left)}
                                </span>
                            )}

                            {box.state === 'sold' && (
                                <span className="absolute inset-x-1 bottom-1 inline-flex items-center justify-center gap-0.5 text-[10px] font-bold">
                                    <PackageOpen aria-hidden="true" className="size-3" />
                                    Sold
                                </span>
                            )}
                        </button>

                        {canLock && (
                            <button
                                type="button"
                                onClick={() => onLockBox(box)}
                                aria-label={`Lock box ${box.box_no} to you`}
                                title="Lock this box to you"
                                className="inline-flex items-center justify-center gap-1 border border-black/15 py-1 text-[10px] font-bold hover:border-status-warning-ink hover:bg-status-warning-tint hover:text-status-warning-ink"
                            >
                                <Lock aria-hidden="true" className="size-3" />
                                Lock
                            </button>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

/** Key for the grid's states and hint markers. */
export function BoxGridLegend() {
    const items = [
        { tile: boxStateMeta.available.tile, label: 'Free' },
        { tile: boxStateMeta.locked_mine.tile, label: 'Held for you' },
        { tile: boxStateMeta.locked_other.tile, label: 'Locked by someone' },
        { tile: boxStateMeta.sold.tile, label: 'Sold (figure revealed)' },
    ];

    return (
        <ul className="flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] text-black/70">
            {items.map((item) => (
                <li key={item.label} className="inline-flex items-center gap-1.5">
                    <span aria-hidden="true" className={`size-4 border ${item.tile}`} />
                    {item.label}
                </li>
            ))}
            <li className="inline-flex items-center gap-1">
                <span className="inline-flex items-center bg-status-good-tint px-1 text-status-good-ink">
                    <BadgeCheck aria-hidden="true" className="size-3" />
                </span>
                Verified exclusion
            </li>
            <li className="inline-flex items-center gap-1">
                <span className="inline-flex items-center gap-0.5 bg-black px-1 text-[9px] font-bold text-white">
                    <XCircle aria-hidden="true" className="size-3" />3
                </span>
                3 figures ruled out, trusted
            </li>
            <li className="inline-flex items-center gap-1">
                <span className="inline-flex items-center gap-0.5 bg-white px-1 text-[9px] font-bold text-black/60 ring-1 ring-black/15">
                    <XCircle aria-hidden="true" className="size-3" />1
                </span>
                1 figure ruled out, unconfirmed
            </li>
        </ul>
    );
}
