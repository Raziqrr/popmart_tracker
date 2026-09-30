import { ArrowRight, Crown } from 'lucide-react';
import { bestBoxesForSku, formatPercent } from '@/lib/popnow';
import type { BoxCandidate } from '@/lib/popnow';
import type { PopNowBox, PopNowSet } from '@/types/popnow';

interface SetChancesProps {
    set: PopNowSet;
    boxes: PopNowBox[];
    /** Chances for boxes you can still get (obtainableChances), so sold or locked boxes are never a bet. */
    chances: Map<number, BoxCandidate[]>;
    selectedSkuId: string | null;
    onSelectSku: (skuId: string | null) => void;
    /** Per figure: a free box in another set with a better chance than any here (betterInOtherSets). */
    betterElsewhere?: Map<string, { set: PopNowSet; box: PopNowBox; probability: number }>;
    /** Switch to that set with the box selected. */
    onViewElsewhere?: (set: PopNowSet, box: PopNowBox, skuId: string) => void;
}

/**
 * Every non-secret figure in the set with its single best shot — which
 * box(es), and how likely. Click a figure to highlight the box(es) tied for
 * that lead in the grid; click again to clear. When another set has a free
 * box that's a better bet for a figure, a second line points there.
 */
export function SetChances({ set, boxes, chances, selectedSkuId, onSelectSku, betterElsewhere, onViewElsewhere }: SetChancesProps) {
    const skus = (set.product.skus ?? []).filter((s) => !s.is_secret);
    const boxNoOf = (boxId: number) => boxes.find((b) => b.id === boxId)?.box_no ?? '?';

    const rows = skus
        .map((sku) => ({ sku, ...bestBoxesForSku(chances, sku.id) }))
        .sort((a, b) => b.probability - a.probability);

    return (
        <section aria-label="Best bet per figure" className="flex flex-col gap-2">
            <h3 className="text-sm font-bold">Best bet per figure</h3>
            <ul className="flex flex-col gap-1">
                {rows.map(({ sku, boxIds, probability }) => {
                    const selected = sku.id === selectedSkuId;
                    const gone = boxIds.length === 0;
                    const boxLabel = gone ? 'No free box' : boxIds.map((id) => `Box ${boxNoOf(id)}`).join(', ');
                    const elsewhere = betterElsewhere?.get(sku.id);
                    return (
                        <li key={sku.id} className="flex flex-col">
                            <button
                                type="button"
                                disabled={gone}
                                onClick={() => onSelectSku(selected ? null : sku.id)}
                                className={`flex w-full items-center gap-2 border p-1.5 text-left text-xs disabled:cursor-default disabled:opacity-40 ${
                                    selected ? 'border-black bg-tile' : 'border-black/10'
                                } ${gone ? '' : 'hover:border-black'}`}
                            >
                                {sku.image_url && <img src={sku.image_url} alt="" className="size-7 shrink-0 object-contain" />}
                                <span className="min-w-0 grow truncate font-medium">
                                    {sku.name}
                                    {sku.is_secret && <Crown aria-label="secret" className="ml-1 inline size-3" fill="currentColor" />}
                                </span>
                                <span className="min-w-0 shrink-0 truncate text-black/60" title={boxLabel}>
                                    {boxLabel}
                                </span>
                                <span className="w-10 shrink-0 text-right font-bold tabular-nums">{gone ? '—' : formatPercent(probability)}</span>
                            </button>
                            {elsewhere && onViewElsewhere && (
                                <button
                                    type="button"
                                    onClick={() => onViewElsewhere(elsewhere.set, elsewhere.box, sku.id)}
                                    title={`View set ${elsewhere.set.set_no} with box ${elsewhere.box.box_no} selected`}
                                    className="flex w-full items-center gap-2 border border-t-0 border-brand/30 bg-brand/5 py-1 pr-1.5 pl-10 text-left text-[11px] text-brand hover:bg-brand/10"
                                >
                                    <span className="min-w-0 grow truncate font-bold">
                                        Higher in set {elsewhere.set.set_no} · Box {elsewhere.box.box_no}
                                    </span>
                                    <span className="w-10 shrink-0 text-right font-bold tabular-nums">{formatPercent(elsewhere.probability)}</span>
                                    <ArrowRight aria-hidden="true" className="size-3.5 shrink-0" />
                                </button>
                            )}
                        </li>
                    );
                })}
            </ul>
            {selectedSkuId && (
                <button type="button" onClick={() => onSelectSku(null)} className="self-start text-[11px] font-bold hover:underline">
                    Clear highlight
                </button>
            )}
        </section>
    );
}
