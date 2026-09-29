import { Crown } from 'lucide-react';
import { bestBoxesForSku, formatPercent } from '@/lib/popnow';
import type { BoxCandidate } from '@/lib/popnow';
import type { PopNowBox, PopNowSet } from '@/types/popnow';

interface SetChancesProps {
    set: PopNowSet;
    boxes: PopNowBox[];
    chances: Map<number, BoxCandidate[]>;
    selectedSkuId: string | null;
    onSelectSku: (skuId: string | null) => void;
}

/**
 * Every non-secret figure in the set with its single best shot — which
 * box(es), and how likely. Click a figure to highlight the box(es) tied for
 * that lead in the grid; click again to clear.
 */
export function SetChances({ set, boxes, chances, selectedSkuId, onSelectSku }: SetChancesProps) {
    const skus = (set.product.skus ?? []).filter((s) => !s.is_secret);
    const boxNoOf = (boxId: number) => boxes.find((b) => b.id === boxId)?.box_no ?? '?';

    // A sold box's own chances entry is its confirmed 100% reveal — correct
    // to show on that box specifically, but not something you can still act
    // on, so it shouldn't count as this figure's "best bet" going forward.
    // Excluding it here naturally sends an already-revealed figure to 0%
    // (its pool is fully consumed elsewhere too), same as "Gone" in SetOdds.
    const openChances = new Map([...chances].filter(([boxId]) => boxes.find((b) => b.id === boxId)?.state !== 'sold'));

    const rows = skus
        .map((sku) => ({
            sku,
            revealed: boxes.some((b) => b.reveal?.sku.id === sku.id),
            ...bestBoxesForSku(openChances, sku.id),
        }))
        .sort((a, b) => b.probability - a.probability);

    return (
        <section aria-label="Best bet per figure" className="flex flex-col gap-2">
            <h3 className="text-sm font-bold">Best bet per figure</h3>
            <ul className="flex flex-col gap-1">
                {rows.map(({ sku, boxIds, probability }) => {
                    const selected = sku.id === selectedSkuId;
                    const gone = boxIds.length === 0;
                    const boxLabel = gone ? 'Gone / unknown' : boxIds.map((id) => `Box ${boxNoOf(id)}`).join(', ');
                    return (
                        <li key={sku.id}>
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
