import { ArrowLeft, Lock } from 'lucide-react';
import { useState } from 'react';
import { AccountNotice, accountBlocker, AutoLockForm, draftIsValid, RiskAcknowledgement, RuleSummary } from '@/components/lock/AutoLockForm';
import { newDraft } from '@/components/lock/lockText';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { SaleTypeBadge } from '@/components/product/SaleTypeBadge';
import { StatusBadge } from '@/components/product/StatusBadge';
import { productStatus } from '@/lib/productStatus';
import type { AutoLockRuleDraft } from '@/types/lock';
import { sampleFigures, sampleLockAccount, sampleLockLimits } from '../fixtures/lock';
import { lockStore, useLockStore } from '../fixtures/lockStore';
import { sampleProducts } from '../fixtures/products';
import { MockSiteFrame } from './MockSiteFrame';

const AUTO_LOCK_ROUTE = '#/page-auto-lock';
const popNowProducts = sampleProducts.filter((p) => p.business_type === 'draw');

/**
 * Full auto-lock setup: every setting on one page. Opened from the quick
 * dialog's "More options" (carrying its draft), or directly to pick a product.
 */
export function AutoLockSetupPage() {
    const { handoff, acknowledged, rules } = useLockStore();
    const [productId, setProductId] = useState(handoff?.product.id ?? popNowProducts[0].id);
    const product = popNowProducts.find((p) => p.id === productId) ?? handoff!.product;
    const existing = handoff?.ruleId ? rules.find((r) => r.id === handoff.ruleId) ?? null : null;

    const [draft, setDraft] = useState<AutoLockRuleDraft>(() => handoff?.draft ?? newDraft(product, sampleLockLimits));
    const [accepted, setAccepted] = useState(acknowledged);

    const blocked = accountBlocker(sampleLockAccount);
    const canSave = draftIsValid(draft, sampleLockLimits) && accepted && !blocked;

    const leave = () => {
        lockStore.setHandoff(null);
        window.location.hash = AUTO_LOCK_ROUTE;
    };

    return (
        <MockSiteFrame currentHref="/auto-lock">
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Auto-lock', href: AUTO_LOCK_ROUTE }, { label: existing ? 'Edit rule' : 'New rule' }]} />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    if (!canSave) return;
                    lockStore.saveRule(product, draft, existing?.id ?? null);
                    leave();
                }}
                className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]"
            >
                <div className="flex flex-col gap-8">
                    <header className="flex flex-col gap-2">
                        <h1 className="flex items-center gap-2 text-2xl font-bold sm:text-3xl">
                            <Lock aria-hidden="true" className="size-7" />
                            {existing ? 'Edit auto-lock' : 'New auto-lock'}
                        </h1>
                        {!handoff && (
                            <label className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                POP NOW product
                                <select
                                    value={productId}
                                    onChange={(e) => {
                                        const next = popNowProducts.find((p) => p.id === e.target.value)!;
                                        setProductId(next.id);
                                        setDraft(newDraft(next, sampleLockLimits));
                                    }}
                                    className="border border-black/20 bg-white px-2 py-1.5 text-sm"
                                >
                                    {popNowProducts.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                    </header>

                    <AutoLockForm draft={draft} onChange={setDraft} figures={sampleFigures[product.id] ?? []} limits={sampleLockLimits} />
                </div>

                {/* Sticky review column: what will happen, then confirm. */}
                <aside className="flex flex-col gap-4 lg:sticky lg:top-32 lg:self-start">
                    <div className="flex items-start gap-3 border border-black/10 p-3">
                        <div className="size-16 shrink-0 bg-tile">
                            {product.image_url && <img src={product.image_url} alt="" className="size-full object-contain p-1" />}
                        </div>
                        <div className="flex min-w-0 flex-col gap-1">
                            <p className="line-clamp-2 text-sm font-bold">{product.name}</p>
                            <span className="flex flex-wrap gap-1.5">
                                <SaleTypeBadge product={product} />
                                <StatusBadge status={productStatus(product)} size="xs" />
                            </span>
                        </div>
                    </div>
                    <RuleSummary draft={draft} />
                    <AccountNotice account={sampleLockAccount} />
                    {!acknowledged && <RiskAcknowledgement checked={accepted} onChange={setAccepted} />}
                    <div className="flex gap-2">
                        <button type="button" onClick={leave} className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold hover:underline">
                            <ArrowLeft aria-hidden="true" className="size-4" />
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={!canSave}
                            className="ml-auto inline-flex items-center gap-1.5 bg-black px-4 py-2 text-xs font-bold tracking-wide text-white uppercase hover:bg-black/80 disabled:cursor-not-allowed disabled:bg-black/20"
                        >
                            <Lock aria-hidden="true" className="size-3.5" />
                            {existing ? 'Save changes' : 'Confirm'}
                        </button>
                    </div>
                </aside>
            </form>
        </MockSiteFrame>
    );
}
