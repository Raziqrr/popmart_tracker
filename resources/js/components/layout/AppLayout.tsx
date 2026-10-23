import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AuthProvider } from '@/lib/capabilities';
import type { SharedProps } from '@/types/page';
import { AccountMenu } from './AccountMenu';
import { SiteFooter } from './SiteFooter';
import { SiteHeader } from './SiteHeader';

/**
 * Header + footer for every Inertia page, fed by the shared props
 * (HandleInertiaRequests). Also provides AuthProvider, so useCan() and
 * RequiresCapability work anywhere below.
 */
export function AppLayout({ title, children }: { title?: string; children: ReactNode }) {
    const { props, url } = usePage<SharedProps>();
    const pinnedCount = props.lists.pinned_product_ids.length + props.lists.pinned_collection_ids.length;

    return (
        <AuthProvider value={props.auth}>
            <Head title={title} />
            <div className="bg-white">
                <SiteHeader
                    area={props.area}
                    currentHref={url.split('?')[0]}
                    unreadAlerts={props.unread_alerts}
                    pinnedCount={pinnedCount}
                    account={<AccountMenu auth={props.auth} />}
                />
                <main className="mx-auto flex max-w-7xl flex-col gap-10 px-4 pt-6 pb-16 sm:px-8">{children}</main>
                <SiteFooter />
            </div>
        </AuthProvider>
    );
}
