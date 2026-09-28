import { Crown } from 'lucide-react';
import { bestBoxesForSku, formatPercent } from '@/lib/popnow';
import type { BoxCandidate } from '@/lib/popnow';
import type { PopNowSet } from '@/types/popnow';

interface SetChancesProps {
    set: PopNowSet;
    chances: Map<number, BoxCandidate[]>;
    selectedSkuId: string | null;
    onSelectSku: (skuId: string | null) => void;
}

/**
 * Every non-secret figure in the set with its single best shot — which box,
 * and how likely. Click a figure to highlight the box(es) tied for that lead
 * in the grid; click again to clear.
 */
export function SetChances({ set, chances, selectedSkuId, onSelectSku }: SetChancesProps) {
    const skus = (set.product.skus ?? []).filter((s) => !s.is_secret);

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
                                <span className="shrink-0 text-black/60">
                                    {gone ? 'Gone / unknown' : `${boxIds.length > 1 ? `${boxIds.length} boxes tied` : '1 box'}`}
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
