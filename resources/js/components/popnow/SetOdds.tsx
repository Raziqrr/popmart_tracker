import { Crown, Sparkles } from 'lucide-react';
import { formatPercent, setOdds } from '@/lib/popnow';
import type { PopNowBox, PopNowSet } from '@/types/popnow';

/**
 * What's left in a set: per figure, how many remain, the plain chance an
 * unopened box holds it (single-series bar), and how many boxes are hinted.
 */
export function SetOdds({ set, boxes }: { set: PopNowSet; boxes: PopNowBox[] }) {
    const { unopened, figures } = setOdds(set, boxes);
    const top = Math.max(...figures.map((f) => f.chance), 0.01);

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
                        <th scope="col" className="px-2 py-1.5 font-bold">Left</th>
                        <th scope="col" className="py-1.5 pl-2 font-bold">Chance per box</th>
                    </tr>
                </thead>
                <tbody>
                    {figures.map((f) => (
                        <tr key={f.sku.id} className={`border-b border-black/10 ${f.remaining === 0 ? 'text-black/35' : ''}`}>
                            <td className="py-1.5 pr-2">
                                <span className="flex items-center gap-2">
                                    {f.sku.image_url && (
                                        <img src={f.sku.image_url} alt="" className={`size-7 shrink-0 object-contain ${f.remaining === 0 ? 'opacity-40 grayscale' : ''}`} />
                                    )}
                                    <span className="flex flex-col">
                                        <span className="inline-flex items-center gap-1 font-medium">
                                            {f.sku.name}
                                            {f.sku.is_secret && <Crown aria-label="secret" className="size-3" fill="currentColor" />}
                                        </span>
                                        {f.hintedBoxes > 0 && (
                                            <span className="inline-flex items-center gap-0.5 text-[10px] text-black/60">
                                                <Sparkles aria-hidden="true" className="size-3" />
                                                hinted in {f.hintedBoxes} box{f.hintedBoxes > 1 ? 'es' : ''}
                                            </span>
                                        )}
                                    </span>
                                </span>
                            </td>
                            <td className="px-2 py-1.5 whitespace-nowrap tabular-nums">
                                <strong>{f.remaining}</strong>
                                <span className="text-black/50"> / {f.inSet}</span>
                            </td>
                            <td className="py-1.5 pl-2">
                                <span className="flex items-center gap-2">
                                    <span className="h-1.5 w-20 shrink-0 bg-tile">
                                        <span className="block h-full rounded-r-sm bg-black" style={{ width: `${(f.chance / top) * 100}%` }} />
                                    </span>
                                    <span className="font-bold tabular-nums">{f.remaining === 0 ? 'Gone' : formatPercent(f.chance)}</span>
                                </span>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <p className="text-[10px] text-black/50">Chance ignores hints: remaining of that figure ÷ unopened boxes.</p>
        </section>
    );
}
