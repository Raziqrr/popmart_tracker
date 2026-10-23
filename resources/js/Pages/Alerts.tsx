import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AlertSettingsForm } from '@/components/alerts/AlertSettingsForm';
import { AlertsInbox } from '@/components/alerts/AlertsInbox';
import { ApiErrorNotice } from '@/components/feedback/ApiErrorNotice';
import { AppLayout } from '@/components/layout/AppLayout';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { fetchAlerts, markAlertsRead, saveAlertSettings } from '@/lib/api';
import type { AlertInbox } from '@/types/alerts';
import type { AlertsPageProps } from '@/types/page';

/** GET /alerts (PageController::alerts, logged in): the inbox and alert settings. */
export default function Alerts() {
    const { props } = usePage<AlertsPageProps>();
    const [inbox, setInbox] = useState<AlertInbox | null>(null);
    const [error, setError] = useState<unknown>(null);

    const load = () =>
        fetchAlerts()
            .then((data) => {
                setInbox(data);
                setError(null);
            })
            .catch(setError);

    useEffect(() => {
        load();
    }, []);

    const markRead = async (ids?: string[]) => {
        if (!inbox) return;
        const now = new Date().toISOString();
        setInbox({ ...inbox, alerts: inbox.alerts.map((a) => (!ids || ids.includes(a.id) ? { ...a, read_at: a.read_at ?? now } : a)) });
        try {
            const { unread } = await markAlertsRead(ids);
            setInbox((current) => (current ? { ...current, unread } : current));
        } catch (e) {
            setError(e);
        }
    };

    return (
        <AppLayout title="Alerts">
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Alerts' }]} />
            <h1 className="text-2xl font-bold sm:text-3xl">Alerts</h1>

            <div className="grid gap-10 lg:grid-cols-[3fr_2fr]">
                <div className="flex flex-col gap-2">
                    {error !== null && <ApiErrorNotice error={error} onRetry={load} />}
                    {inbox ? <AlertsInbox alerts={inbox.alerts} unread={inbox.unread} onMarkRead={markRead} /> : !error && <p className="text-xs text-black/50">Loading…</p>}
                </div>
                <section aria-label="Alert settings" className="flex flex-col gap-3">
                    <AlertSettingsForm value={props.settings} onSave={saveAlertSettings} />
                </section>
            </div>
        </AppLayout>
    );
}
