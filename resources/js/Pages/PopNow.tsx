import { router } from '@inertiajs/react';
import { CalendarClock, Dices, Lock, MousePointerClick } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { AppLayout } from '@/components/layout/AppLayout';
import { CheckoutToggle } from '@/components/lock/AutoLockForm';
import { LockToggle } from '@/components/lock/LockToggle';
import { BoxDetail } from '@/components/popnow/BoxDetail';
import { BoxGrid, BoxGridLegend } from '@/components/popnow/BoxGrid';
import { SetChances } from '@/components/popnow/SetChances';
import { SetList } from '@/components/popnow/SetList';
import { SetOdds } from '@/components/popnow/SetOdds';
import { StatusBadge } from '@/components/product/StatusBadge';
import { formatCountdown, formatShortDateTime } from '@/lib/format';
import { bestBoxesForSku, betterElsewhereFor, betterInOtherSets, boxChances, obtainableChances } from '@/lib/popnow';
import { productStatus } from '@/lib/productStatus';
import type { SkuSummary } from '@/types/catalog';
import type { BoxHint, PopNowBox, PopNowSet } from '@/types/popnow';
import type { PopNowPageProps } from '@/types/page';

const SET_COUNT_OPTIONS = [3, 5, 10] as const;
const HOLD_SECONDS = 300;

export default function PopNow({ products, selected_product_id, sets: initialSets }: PopNowPageProps) {
    const popNowProducts = products.filter((p) => p.business_type === 'draw');
    const [productId, setProductId] = useState(selected_product_id || (popNowProducts[0]?.id ?? ''));
    const [sets, setSets] = useState(initialSets);
    const [checkoutAfterLock, setCheckoutAfterLock] = useState(true);

    useEffect(() => {
        setSets(initialSets);
    }, [initialSets]);

    const product = popNowProducts.find((p) => p.id === productId) ?? popNowProducts[0];

    const allProductSets = useMemo(
        () => sets.filter((entry) => entry.set.product.id === productId),
        [sets, productId],
    );

    const [visibleSetCount, setVisibleSetCount] = useState<number | 'all'>(5);
    const productSets = visibleSetCount === 'all' ? allProductSets : allProductSets.slice(0, visibleSetCount);
    const [setId, setSetId] = useState<number | null>(productSets[0]?.set.id ?? null);

    const current = productSets.find((entry) => entry.set.id === setId) ?? productSets[0] ?? null;
    const [boxId, setBoxId] = useState<number | null>(null);
    const box = current?.boxes.find((b) => b.id === boxId) ?? null;
    const [selectedSkuId, setSelectedSkuId] = useState<string | null>(null);

    // Odds per set
    const chancesBySet = useMemo(
        () => new Map(allProductSets.map((entry) => [entry.set.id, boxChances(entry.set, entry.boxes)])),
        [allProductSets],
    );
    const chances = (current && chancesBySet.get(current.set.id)) || new Map();
    const betChances = useMemo(() => (current ? obtainableChances(chances, current.boxes) : new Map()), [chances, current]);
    const highlight = selectedSkuId ? bestBoxesForSku(betChances, selectedSkuId) : null;
    const highlightedBoxIds = highlight ? new Set(highlight.boxIds) : undefined;

    const betterElsewhere = current
        ? betterInOtherSets(
              betChances,
              productSets.filter((entry) => entry.set.id !== current.set.id).map((entry) => ({ ...entry, chances: chancesBySet.get(entry.set.id) ?? new Map() })),
              (current.set.product.skus ?? []).filter((s) => !s.is_secret).map((s) => s.id),
          )
        : undefined;

    const selectSku = (skuId: string | null) => {
        setSelectedSkuId(skuId);
        if (skuId) {
            const best = bestBoxesForSku(betChances, skuId);
            if (best.boxIds.length > 0) setBoxId(best.boxIds[0]);
        }
    };

    const updateBox = (targetBoxId: number, update: (b: PopNowBox) => PopNowBox) => {
        setSets((all) =>
            all.map((entry) => ({
                ...entry,
                boxes: entry.boxes.map((b) => (b.id === targetBoxId ? update(b) : b)),
            })),
        );
    };

    const handleLockBox = (targetBox: PopNowBox) => {
        const expires = new Date(Date.now() + HOLD_SECONDS * 1000).toISOString();
        updateBox(targetBox.id, (b) => ({ ...b, state: 'locked_mine', lock_expires_at: expires }));
    };

    const handleReleaseBox = (targetBox: PopNowBox) => {
        updateBox(targetBox.id, (b) => ({ ...b, state: 'available', lock_expires_at: null }));
    };

    const handleLockAllInSet = (targetSet: PopNowSet) => {
        const entry = sets.find((e) => e.set.id === targetSet.id);
        if (!entry) return;
        const available = entry.boxes.filter((b) => b.state === 'available');
        const expires = new Date(Date.now() + HOLD_SECONDS * 1000).toISOString();
        available.forEach((b) => {
            updateBox(b.id, (box) => ({ ...box, state: 'locked_mine', lock_expires_at: expires }));
        });
    };

    const handleLockAllSets = (displayed: { set: PopNowSet; boxes: PopNowBox[] }[]) => {
        const expires = new Date(Date.now() + HOLD_SECONDS * 1000).toISOString();
        displayed.forEach(({ boxes }) => {
            boxes.filter((b) => b.state === 'available').forEach((b) => {
                updateBox(b.id, (box) => ({ ...box, state: 'locked_mine', lock_expires_at: expires }));
            });
        });
    };

    const handleSwitchProduct = (newId: string) => {
        setProductId(newId);
        setSetId(null);
        setBoxId(null);
        router.get(
            '/pop-now',
            { product: newId },
            {
                preserveState: true,
                replace: true,
                only: ['sets', 'selected_product_id'],
            },
        );
    };

    return (
        <AppLayout title="POP NOW">
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'POP NOW' }]} />

            <header className="flex flex-col gap-1">
                <h1 className="flex items-center gap-2 text-2xl font-bold sm:text-3xl">
                    <Dices aria-hidden="true" className="size-7" />
                    POP NOW
                </h1>
                <p className="text-sm text-black/60">Live box grids for online blind-box draws: what's taken, what's hinted, what's left.</p>
            </header>

            <div role="tablist" aria-label="POP NOW draws" className="flex gap-2 overflow-x-auto pb-1">
                {popNowProducts.map((p) => (
                    <button
                        key={p.id}
                        type="button"
                        role="tab"
                        aria-selected={p.id === productId}
                        onClick={() => handleSwitchProduct(p.id)}
                        className="flex shrink-0 items-center gap-2 border border-black/15 p-2 pr-3 text-left hover:border-black aria-selected:border-2 aria-selected:border-black"
                    >
                        <span className="size-10 bg-tile">
                            {p.image_url && <img src={p.image_url} alt="" className="size-full object-contain p-1" />}
                        </span>
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
                        {product?.is_coming_soon && product?.sale_start_at
                            ? `Draw opens in ${formatCountdown(product.sale_start_at)}`
                            : 'No sets seen yet'}
                    </p>
                    {product?.sale_start_at && <p className="text-sm text-black/60">{formatShortDateTime(product.sale_start_at)}</p>}
                    {product && (
                        <p className="flex items-center gap-2 text-sm">
                            Set up auto-lock so a box is grabbed the moment it opens:
                            <LockToggle product={product} />
                        </p>
                    )}
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
                        <span className="flex flex-wrap items-center gap-3">
                            <CheckoutToggle compact checked={checkoutAfterLock} onChange={setCheckoutAfterLock} />
                            <button
                                type="button"
                                onClick={() => handleLockAllSets(productSets)}
                                disabled={!productSets.some(({ boxes }) => boxes.some((b) => b.state === 'available'))}
                                title={`Lock every free box across all ${productSets.length} sets shown below to you`}
                                className="inline-flex items-center gap-1.5 border border-black bg-status-warning-tint px-3 py-1.5 text-xs font-bold text-status-warning-ink hover:bg-status-warning disabled:cursor-default disabled:opacity-40"
                            >
                                <Lock aria-hidden="true" className="size-3.5" />
                                Lock All
                            </button>
                        </span>
                    </div>

                    <SetList
                        sets={productSets}
                        selectedId={current.set.id}
                        onSelect={(set) => {
                            setSetId(set.id);
                            setBoxId(null);
                        }}
                        onLockSet={handleLockAllInSet}
                    />

                    <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                        <section aria-label={`Set ${current.set.set_no} box grid`} className="flex flex-col gap-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="text-lg font-bold">Set {current.set.set_no}</h2>
                                {product && (
                                    <span className="flex items-center gap-2 text-xs text-black/60">
                                        Auto-lock this draw
                                        <LockToggle product={product} />
                                    </span>
                                )}
                            </div>
                            <div className="mx-auto w-full max-w-xl">
                                <BoxGrid
                                    set={current.set}
                                    boxes={current.boxes}
                                    selectedId={boxId}
                                    onSelect={(b) => setBoxId(b.id)}
                                    onLockBox={handleLockBox}
                                    highlightedBoxIds={highlightedBoxIds}
                                    highlightProbability={highlight?.probability}
                                />
                            </div>
                            <BoxGridLegend />
                            <SetChances
                                set={current.set}
                                boxes={current.boxes}
                                chances={betChances}
                                selectedSkuId={selectedSkuId}
                                onSelectSku={selectSku}
                                betterElsewhere={betterElsewhere}
                                onViewElsewhere={(set, elsewhereBox, skuId) => {
                                    setSetId(set.id);
                                    setBoxId(elsewhereBox.id);
                                    setSelectedSkuId(skuId);
                                }}
                            />
                        </section>

                        <aside className="flex flex-col gap-6 lg:sticky lg:top-32 lg:self-start">
                            {box ? (
                                <BoxDetail
                                    set={current.set}
                                    box={box}
                                    candidates={chances.get(box.id)}
                                    betterElsewhere={betterElsewhereFor(betChances, current.boxes, box.id)}
                                    onSelectSku={selectSku}
                                    selectedSkuId={selectedSkuId}
                                    onLock={handleLockBox}
                                    onRelease={handleReleaseBox}
                                    payHref={() => 'https://www.popmart.com/my'}
                                    onReportHint={(b: PopNowBox, sku: SkuSummary) => {
                                        updateBox(b.id, (prevBox) => ({
                                            ...prevBox,
                                            hints: [
                                                ...prevBox.hints,
                                                { sku, source: 'user_reported', confirmations: 1, last_seen_at: new Date().toISOString() },
                                            ],
                                        }));
                                    }}
                                    onConfirmHint={(b: PopNowBox, hint: BoxHint) => {
                                        updateBox(b.id, (prevBox) => ({
                                            ...prevBox,
                                            hints: prevBox.hints.map((h) =>
                                                h.sku.id === hint.sku.id ? { ...h, confirmations: h.confirmations + 1 } : h,
                                            ),
                                        }));
                                    }}
                                />
                            ) : (
                                <p className="flex items-center gap-2 border border-dashed border-black/20 p-4 text-sm text-black/60">
                                    <MousePointerClick aria-hidden="true" className="size-5 shrink-0" />
                                    Select a box to see its hints and lock it.
                                </p>
                            )}
                            <SetOdds set={current.set} boxes={current.boxes} chances={chances} selectedSkuId={selectedSkuId} onSelectSku={selectSku} />
                        </aside>
                    </div>
                </>
            )}
        </AppLayout>
    );
}
