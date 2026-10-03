import { Crown, Lock, XCircle } from 'lucide-react';
import { formatPercent, isObtainable, setOdds } from '@/lib/popnow';
import type { BoxCandidate } from '@/lib/popnow';
import type { PopNowBox, PopNowSet } from '@/types/popnow';

interface SetOddsProps {
    set: PopNowSet;
    boxes: PopNowBox[];
    /** This set's boxChances, if the page already has them (saves recomputing). */
    chances?: Map<number, BoxCandidate[]>;
    selectedSkuId?: string | null;
    /** Clicking a figure's row highlights its best box(es) on the grid, like "Best bet per figure". */
    onSelectSku?: (skuId: string | null) => void;
}

/**
 * What's left in a set, as a figure × box heat map: one row per figure, one
 * cell per unopened box, shaded by the exact chance that box holds it (the
 * same boxChances numbers "Best bet per figure" uses, so the panels agree).
 * The row's number is its best chance in a box you can still get. Boxes
 * locked by someone else are hatched: still possible, just not yours to take.
 * Secrets are shown as unknown: nothing in Pop Mart's data ever indicates
 * whether an unrevealed box is a secret slot.
 */
export function SetOdds({ set, boxes, chances, selectedSkuId, onSelectSku }: SetOddsProps) {
    const { unopened, figures } = setOdds(set, boxes, chances);
    const columns = boxes.filter((b) => b.state !== 'sold').sort((a, b) => a.box_no.localeCompare(b.box_no, undefined, { numeric: true }));

    return (
        <section aria-label="What's left in this set" className="flex flex-col gap-3">
            <div className="flex items-baseline justify-between gap-3">
                <h3 className="text-sm font-bold">What's left</h3>
                <p className="text-[11px] text-black/60">
                    {unopened} unopened of {set.total_boxes}
                </p>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-left text-xs">
                    <caption className="sr-only">Chance each unopened box in set {set.set_no} holds each figure</caption>
                    <thead>
                        <tr className="border-b border-black text-[10px] tracking-wider text-black/60 uppercase">
                            <th scope="col" className="py-1.5 pr-2 font-bold">Figure</th>
                            <th scope="col" className="px-1 py-1.5 font-bold">
                                <span className="flex gap-px">
                                    {columns.map((box) => (
                                        <span key={box.id} className="w-4 text-center text-[8px] tabular-nums" title={`Box ${box.box_no}`}>
                                            {box.box_no.slice(-2)}
                                        </span>
                                    ))}
                                </span>
                            </th>
                            <th scope="col" className="py-1.5 pl-2 text-right font-bold">Best</th>
                        </tr>
                    </thead>
                    <tbody>
                        {figures.map((f) => {
                            const selected = f.sku.id === selectedSkuId;
                            const selectable = !!onSelectSku && f.trackable && f.bestChance > 0;

                            return (
                                <tr
                                    key={f.sku.id}
                                    onClick={selectable ? () => onSelectSku(selected ? null : f.sku.id) : undefined}
                                    className={`border-b border-black/10 ${f.revealed ? 'text-black/35' : ''} ${selected ? 'bg-tile' : ''} ${selectable ? 'cursor-pointer hover:bg-tile' : ''}`}
                                >
                                    <th scope="row" className="py-1.5 pr-2 font-normal">
                                        <span className="flex items-center gap-2">
                                            {f.sku.image_url && (
                                                <img src={f.sku.image_url} alt="" className={`size-7 shrink-0 object-contain ${f.revealed ? 'opacity-40 grayscale' : ''}`} />
                                            )}
                                            <span className="flex min-w-0 flex-col">
                                                <span className="inline-flex items-center gap-1 font-medium">
                                                    {f.sku.name}
                                                    {f.sku.is_secret && <Crown aria-label="secret" className="size-3" fill="currentColor" />}
                                                </span>
                                                <span className="inline-flex items-center gap-0.5 text-[10px] text-black/60">
                                                    {f.revealed ? (
                                                        'Revealed'
                                                    ) : !f.trackable ? (
                                                        'Unknown: no hints cover secrets'
                                                    ) : f.excludedFromBoxes > 0 ? (
                                                        <>
                                                            <XCircle aria-hidden="true" className="size-3" />
                                                            ruled out of {f.excludedFromBoxes} box{f.excludedFromBoxes > 1 ? 'es' : ''}
                                                        </>
                                                    ) : (
                                                        `possible in all ${f.possibleBoxes}`
                                                    )}
                                                </span>
                                            </span>
                                        </span>
                                    </th>
                                    <td className="px-1 py-1.5">
                                        <span className="flex gap-px" aria-hidden="true">
                                            {columns.map((box) => (
                                                <HeatCell key={box.id} box={box} chance={f.trackable && !f.revealed ? (f.chanceByBox.get(box.id) ?? 0) : null} />
                                            ))}
                                        </span>
                                    </td>
                                    <td className="py-1.5 pl-2 text-right font-bold whitespace-nowrap tabular-nums">
                                        {f.revealed ? 'Gone' : f.trackable ? formatPercent(f.bestChance) : '—'}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-black/50">
                <span className="inline-flex items-center gap-1">
                    <span className="h-3 w-8" style={{ background: 'linear-gradient(90deg, var(--color-tile), var(--color-brand))' }} />
                    0 → 100% in that box
                </span>
                <span className="inline-flex items-center gap-1">
                    <Lock aria-hidden="true" className="size-3" />
                    hatched: locked by someone else
                </span>
                <span>Best counts only boxes you can still get.</span>
            </p>
        </section>
    );
}

/** One box's chance for one figure. Null = not tracked (secret or already revealed). */
function HeatCell({ box, chance }: { box: PopNowBox; chance: number | null }) {
    const taken = !isObtainable(box);
    const label = chance === null ? '' : `Box ${box.box_no}: ${formatPercent(chance)}${taken ? ' (locked by someone else)' : ''}`;

    return (
        <span
            title={label}
            className="relative size-4 shrink-0 bg-tile"
            style={
                chance
                    ? {
                          background: `color-mix(in srgb, var(--color-brand) ${Math.round(Math.max(chance, 0.06) * 100)}%, var(--color-tile))`,
                      }
                    : undefined
            }
        >
            {taken && (
                <span
                    className="absolute inset-0"
                    style={{ backgroundImage: 'repeating-linear-gradient(45deg, rgb(0 0 0 / 0.35) 0 1px, transparent 1px 4px)' }}
                />
            )}
        </span>
    );
}
