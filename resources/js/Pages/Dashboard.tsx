import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/components/layout/AppLayout';
import { DashboardView } from '@/components/pages/DashboardView';
import { claimTasks } from '@/lib/api';
import { useProductActions } from '@/lib/useProductActions';
import type { DashboardProps } from '@/types/page';

/** GET / (PageController::dashboard). */
export default function Dashboard() {
    const { props } = usePage<DashboardProps>();
    const actions = useProductActions();
    const [luckyPoints, setLuckyPoints] = useState(props.lucky_points);
    const accountId = props.auth.popmart?.session_valid ? props.auth.popmart.account_id : null;

    const pinnedCollections = [...props.pinned_collections, ...props.collections].filter(
        (c, i, all) => actions.pinnedCollectionIds.has(c.id) && all.findIndex((x) => x.id === c.id) === i,
    );

    return (
        <AppLayout title="Dashboard">
            <DashboardView
                products={props.products}
                collections={props.collections}
                pinnedCollections={pinnedCollections}
                events={props.events}
                luckyPoints={luckyPoints}
                actions={actions}
                onUnpinCollection={actions.onTogglePinCollection}
                onClaimTasks={
                    accountId
                        ? async () => {
                              const { results, points } = await claimTasks(accountId);
                              if (points) setLuckyPoints(points);
                              return results;
                          }
                        : undefined
                }
            />
        </AppLayout>
    );
}
