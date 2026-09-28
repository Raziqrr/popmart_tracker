import { CalendarClock, Dices, Lock, MousePointerClick } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { LockToggle } from '@/components/lock/LockToggle';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { BoxDetail } from '@/components/popnow/BoxDetail';
import { BoxGrid, BoxGridLegend } from '@/components/popnow/BoxGrid';
import { SetChances } from '@/components/popnow/SetChances';
import { SetList } from '@/components/popnow/SetList';
import { SetOdds } from '@/components/popnow/SetOdds';
import { StatusBadge } from '@/components/product/StatusBadge';
import { formatCountdown, formatShortDateTime } from '@/lib/format';
import { bestBoxesForSku, betterElsewhereFor, boxChances } from '@/lib/popnow';
import { productStatus } from '@/lib/productStatus';
import { sampleProducts } from '../fixtures/products';
import { usePopNow } from '../fixtures/usePopNow';
import { useHashQuery } from '../useHashQuery';
import { MockSiteFrame } from './MockSiteFrame';

const popNowProducts = sampleProducts.filter((p) => p.business_type === 'draw');
const SET_COUNT_OPTIONS = [3, 5, 10] as const;

/**
 * POP NOW: pick a draw, pick a set, read the box grid (state, hints, locks,
 * reveals), and act on a box. Odds for what's left sit beside the grid.
 * A "Pop Now" badge elsewhere in the mock (SaleTypeBadge) links here as
 * #/page-pop-now?product=<id>, preselecting that draw.
 */
export function PopNowPage() {
    const popNow = usePopNow();
    const linkedProductId = useHashQuery().get('product');
    const [productId, setProductId] = useState(
        popNowProducts.find((p) => p.id === linkedProductId)?.id ?? popNowProducts.find((p) => !p.is_coming_soon)?.id ?? popNowProducts[0].id,
    );
    const product = popNowProducts.find((p) => p.id === productId)!;

    const allProductSets = popNow.sets.filter((entry) => entry.set.product.id === productId);
    const [visibleSetCount, setVisibleSetCount] = useState<number | 'all'>(5);
    const productSets = visibleSetCount === 'all' ? allProductSets : allProductSets.slice(0, visibleSetCount);
    const [setId, setSetId] = useState<number | null>(productSets[0]?.set.id ?? null);
    const current = productSets.find((entry) => entry.set.id === setId) ?? productSets[0] ?? null;
    const [boxId, setBoxId] = useState<number | null>(null);
    const box = current?.boxes.find((b) => b.id === boxId) ?? null;
    const [selectedSkuId, setSelectedSkuId] = useState<string | null>(null);

    // A badge can link here while already on this page (same entry id, new
    // query) — the hash router alone wouldn't notice, so watch it directly.
    useEffect(() => {
        if (linkedProductId && linkedProductId !== productId && popNowProducts.some((p) => p.id === linkedProductId)) {
            setProductId(linkedProductId);
            setSetId(null);
            setBoxId(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [linkedProductId]);

    const chances = useMemo(() => (current ? boxChances(current.set, current.boxes) : new Map()), [current]);
    const highlight = selectedSkuId ? bestBoxesForSku(chances, selectedSkuId) : null;
    const highlightedBoxIds = highlight ? new Set(highlight.boxIds) : undefined;

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
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <label className="flex items-center gap-2 text-xs text-black/60">
                            Show
                            <select
                                value={visibleSetCount}
                                onChange={(e) => setVisibleSetCount(e.target.value === 'all' ? 'all' : Number(e.target.value))}
                                className="border border-black/20 px-1.5 py-1 text-xs font-bold"
                            >
                                {SET_COUNT_OPTIONS.map((n) => (
                                    <option key={n} value={n}>
                                        {n} sets
                                    </option>
                                ))}
                                <option value="all">All ({allProductSets.length})</option>
                            </select>
                        </label>
                        <button
                            type="button"
                            onClick={() => popNow.lockAllSets(productSets)}
                            disabled={!productSets.some(({ boxes }) => boxes.some((b) => b.state === 'available'))}
                            title={`Lock every free box across all ${productSets.length} sets shown below to you`}
                            className="inline-flex items-center gap-1.5 border border-black bg-status-warning-tint px-3 py-1.5 text-xs font-bold text-status-warning-ink hover:bg-status-warning disabled:cursor-default disabled:opacity-40"
                        >
                            <Lock aria-hidden="true" className="size-3.5" />
                            Lock All
                        </button>
                    </div>

                    <SetList
                        sets={productSets}
                        selectedId={current.set.id}
                        onSelect={(set) => {
                            setSetId(set.id);
                            setBoxId(null);
                        }}
                        onLockSet={popNow.lockAllInSet}
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
                                <BoxGrid
                                    set={current.set}
                                    boxes={current.boxes}
                                    selectedId={boxId}
                                    onSelect={(b) => setBoxId(b.id)}
                                    onLockBox={popNow.lockBox}
                                    highlightedBoxIds={highlightedBoxIds}
                                    highlightProbability={highlight?.probability}
                                />
                            </div>
                            <BoxGridLegend />
                            <SetChances set={current.set} boxes={current.boxes} chances={chances} selectedSkuId={selectedSkuId} onSelectSku={setSelectedSkuId} />
                        </section>

                        <aside className="flex flex-col gap-6 lg:sticky lg:top-32 lg:self-start">
                            {box ? (
                                <BoxDetail
                                    set={current.set}
                                    box={box}
                                    candidates={chances.get(box.id)}
                                    betterElsewhere={betterElsewhereFor(chances, current.boxes, box.id)}
                                    onSelectSku={setSelectedSkuId}
                                    selectedSkuId={selectedSkuId}
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
