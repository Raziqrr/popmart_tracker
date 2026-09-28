import { Crown, XCircle } from 'lucide-react';
import { formatPercent, setOdds } from '@/lib/popnow';
import type { PopNowBox, PopNowSet } from '@/types/popnow';

/**
 * What's left in a set: per figure, whether it's revealed yet, the plain
 * chance an unopened box holds it (single-box estimate from that box's own
 * excluded hints — see setOdds), and how many boxes have ruled it out.
 * Secrets are shown as unknown: nothing in Pop Mart's data ever indicates
 * whether an unrevealed box is a secret slot.
 */
export function SetOdds({ set, boxes }: { set: PopNowSet; boxes: PopNowBox[] }) {
    const { unopened, figures } = setOdds(set, boxes);
    const top = Math.max(...figures.filter((f) => f.trackable).map((f) => f.chance), 0.01);

    return (
        <section aria-label="What's left in this set" className="flex flex-col gap-3">
            <div className="flex items-baseline justify-between gap-3">
                <h3 className="text-sm font-bold">What's left</h3>
                <p className="text-[11px] text-black/60">
                    {unopened} unopened of {set.total_boxes}
                </p>
            </div>

            <table className="w-full border-collapse text-left text-xs">
                <caption className="sr-only">Figures left in set {set.set_no} and the chance per unopened box</caption>
                <thead>
                    <tr className="border-b border-black text-[10px] tracking-wider text-black/60 uppercase">
                        <th scope="col" className="py-1.5 pr-2 font-bold">Figure</th>
                        <th scope="col" className="px-2 py-1.5 font-bold">Status</th>
                        <th scope="col" className="py-1.5 pl-2 font-bold">Chance per box</th>
                    </tr>
                </thead>
                <tbody>
                    {figures.map((f) => (
                        <tr key={f.sku.id} className={`border-b border-black/10 ${f.revealed ? 'text-black/35' : ''}`}>
                            <td className="py-1.5 pr-2">
                                <span className="flex items-center gap-2">
                                    {f.sku.image_url && (
                                        <img src={f.sku.image_url} alt="" className={`size-7 shrink-0 object-contain ${f.revealed ? 'opacity-40 grayscale' : ''}`} />
                                    )}
                                    <span className="flex flex-col">
                                        <span className="inline-flex items-center gap-1 font-medium">
                                            {f.sku.name}
                                            {f.sku.is_secret && <Crown aria-label="secret" className="size-3" fill="currentColor" />}
                                        </span>
                                        {f.trackable && f.excludedFromBoxes > 0 && (
                                            <span className="inline-flex items-center gap-0.5 text-[10px] text-black/60">
                                                <XCircle aria-hidden="true" className="size-3" />
                                                ruled out of {f.excludedFromBoxes} box{f.excludedFromBoxes > 1 ? 'es' : ''}
                                            </span>
                                        )}
                                    </span>
                                </span>
                            </td>
                            <td className="px-2 py-1.5 whitespace-nowrap tabular-nums">
                                {f.revealed ? 'Revealed' : f.trackable ? `possible in ${f.possibleBoxes}` : 'unknown'}
                            </td>
                            <td className="py-1.5 pl-2">
                                <span className="flex items-center gap-2">
                                    <span className="h-1.5 w-20 shrink-0 bg-tile">
                                        {f.trackable && (
                                            <span className="block h-full rounded-r-sm bg-black" style={{ width: `${(f.chance / top) * 100}%` }} />
                                        )}
                                    </span>
                                    <span className="font-bold tabular-nums">
                                        {f.revealed ? 'Gone' : f.trackable ? formatPercent(f.chance) : '—'}
                                    </span>
                                </span>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <p className="text-[10px] text-black/50">
                Chance per box is a same-box estimate from that box's own tip-card exclusions — not the full
                cross-box calculation the backend runs once more sets are scraped.
            </p>
        </section>
    );
}
