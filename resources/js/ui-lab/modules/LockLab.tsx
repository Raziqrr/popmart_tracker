import { useState } from 'react';
import { ActiveLocks } from '@/components/lock/ActiveLocks';
import { AutoLockDialog } from '@/components/lock/AutoLockDialog';
import { RuleSummary } from '@/components/lock/AutoLockForm';
import { LockHistory } from '@/components/lock/LockHistory';
import { LockPicker } from '@/components/lock/LockPicker';
import { LockRules } from '@/components/lock/LockRules';
import { LockToggle } from '@/components/lock/LockToggle';
import { newDraft } from '@/components/lock/lockText';
import type { AutoLockRuleDraft } from '@/types/lock';
import { sampleFigures, sampleLockAccount, sampleLockAttempts, sampleLockLimits, sampleLockRules } from '../fixtures/lock';
import { productBySlug } from '../fixtures/products';
import { LOCK_SETUP_ROUTE, useAutoLock } from '../fixtures/useAutoLock';
import { LabHeader, Specimen, SpecimenGrid } from '../Specimen';

const drawOnSale = productBySlug('pebble-pals-midnight-snack');
const drawComingSoon = productBySlug('pebble-pals-tiny-garden');

type DialogCase = 'new' | 'first-time' | 'coming-soon' | 'edit' | 'expired-session';

const dialogCases: Record<DialogCase, { label: string; props: Omit<Parameters<typeof AutoLockDialog>[0], 'open' | 'onSave' | 'onClose' | 'figures' | 'limits'> }> = {
    new: { label: 'New rule', props: { product: drawOnSale, account: sampleLockAccount, acknowledged: true } },
    'first-time': { label: 'First rule ever (needs acknowledgement)', props: { product: drawOnSale, account: sampleLockAccount, acknowledged: false } },
    'coming-soon': { label: 'Coming-soon draw', props: { product: drawComingSoon, account: sampleLockAccount, acknowledged: true } },
    edit: { label: 'Edit existing rule', props: { product: drawOnSale, rule: sampleLockRules[0], account: sampleLockAccount, acknowledged: true } },
    'expired-session': {
        label: 'Session expired',
        props: { product: drawOnSale, account: { ...sampleLockAccount, session_valid: false }, acknowledged: true },
    },
};

export function LockLab() {
    const lock = useAutoLock();
    const [dialog, setDialog] = useState<DialogCase | null>(null);
    const [pickerDraft, setPickerDraft] = useState<AutoLockRuleDraft>(() => newDraft(drawOnSale, sampleLockLimits));

    return (
        <div className="flex flex-col gap-12">
            <LabHeader
                title="Auto-lock"
                description="POP NOW only. Lock button, quick-setup dialog (Random count or Specific figures with counts), full setup page, held boxes, rules and history. Backend not built: docs/tickets/auto-lock.md."
                source="components/lock/"
            />

            <SpecimenGrid>
                <Specimen label="Lock button: off" note="Shows only on business_type = draw">
                    <LockToggle product={drawOnSale} active={false} onClick={() => {}} />
                </Specimen>
                <Specimen label="Lock button: rule on" note="Amber = acts on your account; snaps shut when switched on">
                    <LockToggle product={drawOnSale} active onClick={() => {}} />
                </Specimen>
            </SpecimenGrid>

            <Specimen label="LockPicker" note="Random: set a count. Specific: click a figure to add, click again for more, or use − +. No limit on boxes.">
                <div className="flex max-w-xl flex-col gap-3">
                    <LockPicker
                        value={pickerDraft.target}
                        figures={sampleFigures[drawOnSale.id]}
                        onChange={(target) => setPickerDraft((d) => ({ ...d, target, trigger: target.kind === 'specific' ? 'hint_match' : 'restock' }))}
                    />
                    <RuleSummary draft={pickerDraft} />
                </div>
            </Specimen>

            <Specimen label="Quick-setup dialog" note="Open each state">
                <div className="flex flex-wrap gap-2">
                    {(Object.keys(dialogCases) as DialogCase[]).map((key) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setDialog(key)}
                            className="border border-black/20 px-3 py-1.5 text-xs font-bold hover:border-black"
                        >
                            {dialogCases[key].label}
                        </button>
                    ))}
                    <a href={LOCK_SETUP_ROUTE} className="border border-black px-3 py-1.5 text-xs font-bold hover:bg-black hover:text-white">
                        Full setup page →
                    </a>
                </div>
                {dialog && (
                    <AutoLockDialog
                        key={dialog}
                        open
                        {...dialogCases[dialog].props}
                        figures={sampleFigures[dialogCases[dialog].props.product.id] ?? []}
                        limits={sampleLockLimits}
                        onSave={() => setDialog(null)}
                        onDelete={() => setDialog(null)}
                        onMoreOptions={() => setDialog(null)}
                        onClose={() => setDialog(null)}
                    />
                )}
            </Specimen>

            <Specimen label="ActiveLocks" note="Live countdown; turns red under 1 minute">
                <ActiveLocks locks={lock.attempts} onRelease={lock.releaseLock} />
            </Specimen>

            <Specimen label="LockRules" note="Switch, edit, progress and last attempt">
                <LockRules rules={lock.rules} attempts={lock.attempts} onToggle={lock.toggleRule} onEdit={lock.editRule} />
            </Specimen>

            <Specimen label="LockHistory">
                <LockHistory attempts={sampleLockAttempts} />
            </Specimen>

            <Specimen label="Empty states">
                <div className="flex flex-col gap-4">
                    <ActiveLocks locks={[]} />
                    <LockRules rules={[]} attempts={[]} />
                </div>
            </Specimen>

        </div>
    );
}
