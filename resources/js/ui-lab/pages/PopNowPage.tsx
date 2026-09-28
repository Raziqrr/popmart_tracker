import { CalendarClock, Dices, MousePointerClick } from 'lucide-react';
import { useState } from 'react';
import { LockToggle } from '@/components/lock/LockToggle';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { BoxDetail } from '@/components/popnow/BoxDetail';
import { BoxGrid, BoxGridLegend } from '@/components/popnow/BoxGrid';
import { SetList } from '@/components/popnow/SetList';
import { SetOdds } from '@/components/popnow/SetOdds';
import { StatusBadge } from '@/components/product/StatusBadge';
import { formatCountdown, formatShortDateTime } from '@/lib/format';
import { productStatus } from '@/lib/productStatus';
import { sampleProducts } from '../fixtures/products';
import { usePopNow } from '../fixtures/usePopNow';
import { MockSiteFrame } from './MockSiteFrame';

const popNowProducts = sampleProducts.filter((p) => p.business_type === 'draw');

/**
 * POP NOW: pick a draw, pick a set, read the box grid (state, hints, locks,
 * reveals), and act on a box. Odds for what's left sit beside the grid.
 */
export function PopNowPage() {
    const popNow = usePopNow();
    const [productId, setProductId] = useState(popNowProducts.find((p) => !p.is_coming_soon)?.id ?? popNowProducts[0].id);
    const product = popNowProducts.find((p) => p.id === productId)!;

    const productSets = popNow.sets.filter((entry) => entry.set.product.id === productId);
    const [setId, setSetId] = useState<number | null>(productSets[0]?.set.id ?? null);
    const current = productSets.find((entry) => entry.set.id === setId) ?? productSets[0] ?? null;
    const [boxId, setBoxId] = useState<number | null>(null);
    const box = current?.boxes.find((b) => b.id === boxId) ?? null;

    return (
        <MockSiteFrame currentHref="/pop-now">
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'POP NOW' }]} />

            <header className="flex flex-col gap-1">
                <h1 className="flex items-center gap-2 text-2xl font-bold sm:text-3xl">
                    <Dices aria-hidden="true" className="size-7" />
                    POP NOW
                </h1>
                <p className="text-sm text-black/60">Live box grids for online blind-box draws: what's taken, what's hinted, what's left.</p>
            </header>

            <div role="tablist" aria-label="POP NOW draws" className="flex gap-2 overflow-x-auto">
                {popNowProducts.map((p) => (
                    <button
                        key={p.id}
                        type="button"
                        role="tab"
                        aria-selected={p.id === productId}
                        onClick={() => {
                            setProductId(p.id);
                            setSetId(popNow.sets.find((entry) => entry.set.product.id === p.id)?.set.id ?? null);
                            setBoxId(null);
                        }}
                        className="flex shrink-0 items-center gap-2 border border-black/15 p-2 pr-3 text-left hover:border-black aria-selected:border-2 aria-selected:border-black"
                    >
                        <span className="size-10 bg-tile">{p.image_url && <img src={p.image_url} alt="" className="size-full object-contain p-1" />}</span>
                        <span className="flex flex-col gap-0.5">
                            <span className="line-clamp-1 max-w-56 text-xs font-bold">{p.name}</span>
                            <StatusBadge status={productStatus(p)} size="xs" />
                        </span>
                    </button>
                ))}
            </div>

            {!current ? (
                <section className="flex flex-col items-center gap-3 border border-dashed border-black/20 px-4 py-12 text-center">
                    <CalendarClock aria-hidden="true" className="size-8 text-brand" />
                    <p className="text-lg font-bold">
                        {product.is_coming_soon && product.sale_start_at ? `Draw opens in ${formatCountdown(product.sale_start_at)}` : 'No sets seen yet'}
                    </p>
                    {product.sale_start_at && <p className="text-sm text-black/60">{formatShortDateTime(product.sale_start_at)}</p>}
                    <p className="flex items-center gap-2 text-sm">
                        Set up auto-lock so a box is grabbed the moment it opens:
                        <LockToggle product={product} />
                    </p>
                </section>
            ) : (
                <>
                    <SetList
                        sets={productSets}
                        selectedId={current.set.id}
                        onSelect={(set) => {
                            setSetId(set.id);
                            setBoxId(null);
                        }}
                    />

                    <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                        <section aria-label={`Set ${current.set.set_no} box grid`} className="flex flex-col gap-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="text-lg font-bold">Set {current.set.set_no}</h2>
                                <span className="flex items-center gap-2 text-xs text-black/60">
                                    Auto-lock this draw
                                    <LockToggle product={product} />
                                </span>
                            </div>
                            <div className="mx-auto w-full max-w-xl">
                                <BoxGrid set={current.set} boxes={current.boxes} selectedId={boxId} onSelect={(b) => setBoxId(b.id)} />
                            </div>
                            <BoxGridLegend />
                        </section>

                        <aside className="flex flex-col gap-6 lg:sticky lg:top-32 lg:self-start">
                            {box ? (
                                <BoxDetail
                                    set={current.set}
                                    box={box}
                                    onLock={popNow.lockBox}
                                    onRelease={popNow.releaseBox}
                                    payHref={() => 'https://www.popmart.com/my'}
                                    onReportHint={popNow.reportHint}
                                    onConfirmHint={popNow.confirmHint}
                                />
                            ) : (
                                <p className="flex items-center gap-2 border border-dashed border-black/20 p-4 text-sm text-black/60">
                                    <MousePointerClick aria-hidden="true" className="size-5 shrink-0" />
                                    Select a box to see its hints and lock it.
                                </p>
                            )}
                            <SetOdds set={current.set} boxes={current.boxes} />
                        </aside>
                    </div>
                </>
            )}
        </MockSiteFrame>
    );
}
