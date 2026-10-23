import { router, usePage } from '@inertiajs/react';
import { ArrowLeft, History, ListChecks, Lock, ShieldAlert, SlidersHorizontal } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { AppLayout } from '@/components/layout/AppLayout';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { Panel } from '@/components/dashboard/Panel';
import { ActiveLocks } from '@/components/lock/ActiveLocks';
import {
    AccountNotice,
    accountBlocker,
    AutoLockForm,
    draftIsValid,
    RiskAcknowledgement,
    RuleSummary,
} from '@/components/lock/AutoLockForm';
import { LockHistory } from '@/components/lock/LockHistory';
import { LockRules } from '@/components/lock/LockRules';
import { newDraft } from '@/components/lock/lockText';
import { ProductTable } from '@/components/product/ProductTable';
import { SaleTypeBadge } from '@/components/product/SaleTypeBadge';
import { StatusBadge } from '@/components/product/StatusBadge';
import { createAutoLockRule, deleteAutoLockRule, updateAutoLockRule } from '@/lib/api';
import { productStatus } from '@/lib/productStatus';
import type { AutoLockPageProps } from '@/types/page';
import type { AutoLockRule, AutoLockRuleDraft, LockAttempt, LockFigure } from '@/types/lock';
import type { ProductCardData } from '@/types/catalog';

export default function AutoLock({
    products,
    rules: initialRules,
    attempts: initialAttempts,
    limits,
    account,
}: AutoLockPageProps) {
    const { url } = usePage();
    const searchParams = useMemo(() => new URLSearchParams(url.includes('?') ? url.split('?')[1] : ''), [url]);
    const isSetupMode = searchParams.get('mode') === 'setup';
    const preselectedProductId = searchParams.get('product');
    const preselectedRuleId = searchParams.get('rule');

    const [rules, setRules] = useState<AutoLockRule[]>(initialRules);
    const [attempts, setAttempts] = useState(initialAttempts);

    // Setup mode state
    const popNowProducts = useMemo(() => products.filter((p) => p.business_type === 'draw'), [products]);
    const [setupProductId, setSetupProductId] = useState(
        preselectedProductId || popNowProducts.find((p) => !p.is_coming_soon)?.id || popNowProducts[0]?.id || '',
    );
    const [editingRuleId, setEditingRuleId] = useState<string | null>(preselectedRuleId);

    const setupProduct = popNowProducts.find((p) => p.id === setupProductId) ?? popNowProducts[0];
    const existingRule = editingRuleId ? rules.find((r) => r.id === editingRuleId) ?? null : null;

    const [draft, setDraft] = useState<AutoLockRuleDraft>(() => newDraft(setupProduct, limits));
    const [accepted, setAccepted] = useState(true);

    const blocked = accountBlocker(account);
    const canSave = draftIsValid(draft, limits) && accepted && !blocked;

    const figuresForProduct = useCallback((p: ProductCardData): LockFigure[] => {
        return (p.skus ?? []).map((sku) => ({
            sku_id: sku.id,
            name: sku.name,
            image_url: sku.image_url ?? null,
            is_secret: sku.is_secret,
        }));
    }, []);

    const handleToggleRule = async (rule: AutoLockRule) => {
        const nextEnabled = !rule.enabled;
        setRules((prev) => prev.map((r) => (r.id === rule.id ? { ...r, enabled: nextEnabled } : r)));
        try {
            await updateAutoLockRule(rule.id, { enabled: nextEnabled });
        } catch {
            setRules((prev) => prev.map((r) => (r.id === rule.id ? { ...r, enabled: rule.enabled } : r)));
        }
    };

    const handleEditRule = (rule: AutoLockRule) => {
        setEditingRuleId(rule.id);
        setSetupProductId(rule.product.id);
        router.visit(`/auto-lock?mode=setup&product=${rule.product.id}&rule=${rule.id}`);
    };

    const handleReleaseLock = (attempt: LockAttempt) => {
        setAttempts((prev) =>
            prev.map((a) => (a.id === attempt.id ? { ...a, status: 'released', lock_expires_at: null } : a)),
        );
    };

    const handleSaveSetup = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!canSave || !setupProduct) return;

        try {
            const saved = await createAutoLockRule(setupProduct.id, draft, accepted);
            setRules((prev) => [...prev.filter((r) => r.id !== saved.id), saved]);
            router.visit('/auto-lock');
        } catch {
            // handle error if needed
        }
    };

    if (isSetupMode && setupProduct) {
        return (
            <AppLayout title="Auto-Lock Setup">
                <Breadcrumbs
                    items={[
                        { label: 'Home', href: '/' },
                        { label: 'Auto-lock', href: '/auto-lock' },
                        { label: existingRule ? 'Edit rule' : 'New rule' },
                    ]}
                />

                <form onSubmit={handleSaveSetup} className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="flex flex-col gap-8">
                        <header className="flex flex-col gap-2">
                            <h1 className="flex items-center gap-2 text-2xl font-bold sm:text-3xl">
                                <Lock aria-hidden="true" className="size-7" />
                                {existingRule ? 'Edit auto-lock' : 'New auto-lock'}
                            </h1>
                            <label className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                POP NOW product
                                <select
                                    value={setupProductId}
                                    onChange={(e) => {
                                        setSetupProductId(e.target.value);
                                        const nextProduct = popNowProducts.find((p) => p.id === e.target.value);
                                        if (nextProduct) {
                                            setDraft(newDraft(nextProduct, limits));
                                        }
                                    }}
                                    className="border border-black px-2 py-1 text-sm font-bold"
                                >
                                    {popNowProducts.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </header>

                        <AutoLockForm
                            figures={figuresForProduct(setupProduct)}
                            limits={limits}
                            draft={draft}
                            onChange={setDraft}
                        />
                    </div>

                    <aside className="flex flex-col gap-6 lg:sticky lg:top-32 lg:self-start">
                        <div className="flex items-center gap-3 border border-black/10 bg-white p-3">
                            <div className="size-14 shrink-0 bg-tile">
                                {setupProduct.image_url && (
                                    <img src={setupProduct.image_url} alt="" className="size-full object-contain p-1" />
                                )}
                            </div>
                            <div className="min-w-0 grow">
                                <p className="line-clamp-1 text-sm font-bold">{setupProduct.name}</p>
                                <div className="mt-1 flex items-center gap-2">
                                    <StatusBadge status={productStatus(setupProduct)} size="xs" />
                                    <SaleTypeBadge product={setupProduct} />
                                </div>
                            </div>
                        </div>

                        <RuleSummary draft={draft} />

                        <AccountNotice account={account} />

                        <RiskAcknowledgement checked={accepted} onChange={setAccepted} />

                        <div className="flex flex-col gap-2">
                            <button
                                type="submit"
                                disabled={!canSave}
                                className="border border-black bg-black py-2.5 text-center text-xs font-bold text-white hover:bg-black/85 disabled:cursor-not-allowed disabled:bg-black/30"
                            >
                                {existingRule ? 'Save changes' : 'Create rule'}
                            </button>
                            <a
                                href="/auto-lock"
                                className="inline-flex items-center justify-center gap-1.5 py-2 text-xs font-medium text-black/60 hover:text-black"
                            >
                                <ArrowLeft aria-hidden="true" className="size-3.5" />
                                Back to Auto-lock
                            </a>
                        </div>
                    </aside>
                </form>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Auto-Lock">
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Auto-lock' }]} />

            <header className="flex flex-col gap-3">
                <h1 className="flex items-center gap-2 text-2xl font-bold sm:text-3xl">
                    <Lock aria-hidden="true" className="size-7" />
                    Auto-lock
                </h1>
                <p className="flex max-w-3xl items-start gap-2 bg-status-warning-tint p-3 text-xs text-status-warning-ink">
                    <ShieldAlert aria-hidden="true" className="size-4 shrink-0" />
                    Auto-lock holds POP NOW boxes on your Pop Mart account when a rule fires, then alerts you to pay before the hold runs out. It
                    acts on your real account and may break Pop Mart's terms.
                </p>
            </header>

            <Panel title="Held for you" icon={Lock}>
                <ActiveLocks
                    locks={attempts}
                    onRelease={handleReleaseLock}
                    payHref={() => 'https://www.popmart.com/my'}
                />
            </Panel>

            <Panel title="Rules" icon={ListChecks}>
                <LockRules
                    rules={rules}
                    attempts={attempts}
                    onToggle={handleToggleRule}
                    onEdit={handleEditRule}
                />
            </Panel>

            <Panel title="Add a rule" icon={Lock}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-xs text-black/60">Use a product's lock button for quick setup, or open the full setup.</p>
                    <a
                        href="/auto-lock?mode=setup"
                        className="inline-flex items-center gap-1.5 border border-black px-3 py-1.5 text-xs font-bold hover:bg-black hover:text-white"
                    >
                        <SlidersHorizontal aria-hidden="true" className="size-3.5" />
                        Full setup
                    </a>
                </div>
                <ProductTable products={popNowProducts} caption="POP NOW products" compact />
            </Panel>

            <Panel title="History" icon={History}>
                <LockHistory attempts={attempts} />
            </Panel>
        </AppLayout>
    );
}
