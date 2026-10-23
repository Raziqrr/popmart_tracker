import { LoaderCircle, Save } from 'lucide-react';
import { useState } from 'react';
import { ApiErrorNotice } from '@/components/feedback/ApiErrorNotice';
import { changeMeta } from '@/components/product/changeMeta';
import type { AlertSettings } from '@/types/alerts';
import type { ProductEventType } from '@/types/catalog';

interface AlertSettingsFormProps {
    value: AlertSettings;
    /** Saves (PUT /api/alert-settings) and resolves with what the server stored. */
    onSave: (settings: AlertSettings) => Promise<AlertSettings>;
}

const FALLBACK_TYPES: ProductEventType[] = ['restocked', 'sale_started', 'sale_time_changed', 'preorder_opened', 'preorder_closed', 'low_stock', 'sold_out', 'price_drop', 'listed'];

/**
 * Which product events alert you and where they go. The in-app inbox is
 * always on; email and Telegram are opt-in. Watching a collection only
 * alerts for new listings, sale openings and pre-orders, whatever is ticked.
 */
export function AlertSettingsForm({ value, onSave }: AlertSettingsFormProps) {
    const [draft, setDraft] = useState(value);
    const [state, setState] = useState<{ kind: 'idle' | 'saving' | 'saved' } | { kind: 'error'; error: unknown }>({ kind: 'idle' });
    const types = value.available_types ?? FALLBACK_TYPES;

    const toggleType = (type: ProductEventType) =>
        setDraft((d) => ({ ...d, types: d.types.includes(type) ? d.types.filter((t) => t !== type) : [...d.types, type] }));

    const save = async () => {
        setState({ kind: 'saving' });
        try {
            setDraft(await onSave(draft));
            setState({ kind: 'saved' });
        } catch (error) {
            setState({ kind: 'error', error });
        }
    };

    return (
        <form
            className="flex flex-col gap-4 text-xs"
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            <fieldset className="flex flex-col gap-1.5">
                <legend className="mb-1 text-sm font-bold">Alert me when a watched product…</legend>
                <div className="grid grid-cols-1 gap-1 sm:grid-cols-2">
                    {types.map((type) => {
                        const meta = changeMeta[type];
                        const Icon = meta.icon;
                        return (
                            <label key={type} className="flex cursor-pointer items-center gap-2 border border-black/10 p-1.5 has-checked:border-black">
                                <input type="checkbox" checked={draft.types.includes(type)} onChange={() => toggleType(type)} className="accent-black" />
                                <Icon aria-hidden="true" className={`size-3.5 ${meta.ink}`} />
                                <span className="font-medium">{meta.label}</span>
                            </label>
                        );
                    })}
                </div>
            </fieldset>

            <fieldset className="flex flex-col gap-1.5">
                <legend className="mb-1 text-sm font-bold">Send alerts to</legend>
                <label className="flex items-center gap-2 text-black/60">
                    <input type="checkbox" checked disabled className="accent-black" />
                    Inbox on Popmart Tracker (always on)
                </label>
                <label className="flex items-center gap-2">
                    <input type="checkbox" checked={draft.email} onChange={(e) => setDraft((d) => ({ ...d, email: e.target.checked }))} className="accent-black" />
                    Email
                </label>
                <label className={`flex items-center gap-2 ${value.telegram_available === false ? 'text-black/40' : ''}`}>
                    <input
                        type="checkbox"
                        checked={draft.telegram}
                        disabled={value.telegram_available === false}
                        onChange={(e) => setDraft((d) => ({ ...d, telegram: e.target.checked }))}
                        className="accent-black"
                    />
                    Telegram{value.telegram_available === false && ' (not set up on this server)'}
                </label>
                {draft.telegram && (
                    <label className="flex flex-col gap-1 pl-6">
                        <span className="text-black/60">Your Telegram chat id</span>
                        <input
                            value={draft.telegram_chat_id ?? ''}
                            onChange={(e) => setDraft((d) => ({ ...d, telegram_chat_id: e.target.value || null }))}
                            inputMode="numeric"
                            className="w-48 border border-black/20 px-2 py-1"
                            required
                        />
                    </label>
                )}
            </fieldset>

            <div className="flex items-center gap-3">
                <button type="submit" disabled={state.kind === 'saving'} className="inline-flex items-center gap-1.5 bg-black px-3 py-1.5 font-bold text-white hover:bg-black/80 disabled:bg-black/30">
                    {state.kind === 'saving' ? <LoaderCircle aria-hidden="true" className="size-3.5 animate-spin" /> : <Save aria-hidden="true" className="size-3.5" />}
                    Save
                </button>
                {state.kind === 'saved' && (
                    <span role="status" className="font-bold text-good-text">
                        Saved
                    </span>
                )}
            </div>
            {state.kind === 'error' && <ApiErrorNotice error={state.error} onRetry={save} compact />}
        </form>
    );
}
