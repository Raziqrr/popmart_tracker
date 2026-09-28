import { Crown } from 'lucide-react';
import { formatPercent } from '@/lib/popnow';
import type { BoxCandidate } from '@/lib/popnow';

interface ChanceListProps {
    candidates: BoxCandidate[];
    /** Clicking a row calls this with the sku id — parent decides what happens (e.g. highlight boxes). */
    onSelectSku?: (skuId: string) => void;
    selectedSkuId?: string | null;
    emptyLabel?: string;
}

/** One figure per row with a probability bar — shared by the per-box and set-wide chance views. */
export function ChanceList({ candidates, onSelectSku, selectedSkuId, emptyLabel = 'No candidates left.' }: ChanceListProps) {
    if (candidates.length === 0) {
        return <p className="text-xs text-black/50">{emptyLabel}</p>;
    }

    const top = Math.max(...candidates.map((c) => c.probability), 0.01);

    return (
        <ul className="flex flex-col gap-1">
            {candidates.map((c) => {
                const selected = c.sku.id === selectedSkuId;
                const Row = onSelectSku ? 'button' : 'div';
                return (
                    <li key={c.sku.id}>
                        <Row
                            type={onSelectSku ? 'button' : undefined}
                            onClick={onSelectSku ? () => onSelectSku(c.sku.id) : undefined}
                            className={`flex w-full items-center gap-2 border p-1.5 text-left text-xs ${
                                selected ? 'border-black bg-tile' : 'border-black/10'
                            } ${onSelectSku ? 'hover:border-black' : ''}`}
                        >
                            {c.sku.image_url && <img src={c.sku.image_url} alt="" className="size-7 shrink-0 object-contain" />}
                            <span className="min-w-0 grow truncate font-medium">
                                {c.sku.name}
                                {c.sku.is_secret && <Crown aria-label="secret" className="ml-1 inline size-3" fill="currentColor" />}
                            </span>
                            <span className="h-1.5 w-14 shrink-0 bg-tile">
                                <span className="block h-full rounded-r-sm bg-black" style={{ width: `${(c.probability / top) * 100}%` }} />
                            </span>
                            <span className="w-10 shrink-0 text-right font-bold tabular-nums">{formatPercent(c.probability)}</span>
                        </Row>
                    </li>
                );
            })}
        </ul>
    );
}
