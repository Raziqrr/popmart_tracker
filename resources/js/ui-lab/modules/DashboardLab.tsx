import { CalendarClock, Flame, PackageCheck } from 'lucide-react';
import { ActivityFeed } from '@/components/dashboard/ActivityFeed';
import { CollectionStatus } from '@/components/dashboard/CollectionStatus';
import { FastSellers } from '@/components/dashboard/FastSellers';
import { LuckyPointsTile } from '@/components/dashboard/LuckyPointsTile';
import { NewListings } from '@/components/dashboard/NewListings';
import { StatRow } from '@/components/dashboard/StatRow';
import { UpcomingDrops } from '@/components/dashboard/UpcomingDrops';
import { fakeRedeem, fakeRedeemFailure, sampleLuckyPoints, sampleLuckyPointsStates } from '../fixtures/account';
import { sampleCollections } from '../fixtures/collections';
import { sampleEvents } from '../fixtures/events';
import { samplePinnedIds, sampleProducts, sampleWatchedIds } from '../fixtures/products';
import { useProductActions } from '../fixtures/useProductActions';
import { LabHeader, Specimen, SpecimenGrid } from '../Specimen';

export function DashboardLab() {
    const actions = useProductActions(samplePinnedIds, sampleWatchedIds);

    return (
        <div className="flex flex-col gap-12">
            <LabHeader
                title="Dashboard widgets"
                description="Stat row, lucky points, pinned-collection status, new listings, fast sellers, activity feed and upcoming drops."
                source="components/dashboard/"
            />

            <Specimen label="StatRow" note="Pinned-first figures, catalog-wide as detail; 'estimate' marks unbacked numbers">
                <StatRow
                    lead={<LuckyPointsTile points={sampleLuckyPoints} onRedeem={fakeRedeem} />}
                    stats={[
                        { id: 'a', label: 'Restocks 24h', icon: PackageCheck, value: 1, detail: 'pinned · 1 overall' },
                        { id: 'b', label: 'Next drop', icon: CalendarClock, value: '5h 18m', detail: 'Pebble Pals Tiny Garden' },
                        { id: 'c', label: 'Fastest seller', icon: Flame, value: '14/hr', detail: 'Lumi Starlight Voyage', estimate: true },
                    ]}
                />
            </Specimen>

            <section className="flex flex-col gap-6">
                <h2 className="text-lg font-bold">Lucky points</h2>
                <SpecimenGrid>
                    {sampleLuckyPointsStates.map((state) => (
                        <Specimen key={state.label} label={state.label} note={state.note}>
                            <div className="border border-black/10">
                                <LuckyPointsTile points={state.points} onRedeem={fakeRedeem} />
                            </div>
                        </Specimen>
                    ))}
                    <Specimen label="Redeem fails" note="Click Redeem → Confirm">
                        <div className="border border-black/10">
                            <LuckyPointsTile points={sampleLuckyPoints} onRedeem={fakeRedeemFailure} />
                        </div>
                    </Specimen>
                </SpecimenGrid>
            </section>

            <section className="flex flex-col gap-6">
                <h2 className="text-lg font-bold">Pinned collection status</h2>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {sampleCollections.map((collection) => (
                        <CollectionStatus key={collection.id} collection={collection} href={`#${collection.slug}`} onUnpin={() => {}} />
                    ))}
                </div>
            </section>

            <div className="grid gap-8 lg:grid-cols-2">
                <Specimen label="NewListings" note="Tabs: products / collections, by first_seen_at">
                    <NewListings products={sampleProducts} collections={sampleCollections} {...actions} />
                </Specimen>
                <Specimen label="FastSellers" note="sell_rate_per_hour is a placeholder (see ticket)">
                    <FastSellers products={sampleProducts} {...actions} />
                </Specimen>
                <Specimen label="ActivityFeed" note="Filter by kind, pinned only">
                    <ActivityFeed events={sampleEvents} pinnedIds={actions.pinnedIds} productHref={actions.productHref} limit={5} />
                </Specimen>
                <Specimen label="UpcomingDrops" note="Live countdown, reminder bell">
                    <UpcomingDrops products={sampleProducts} {...actions} />
                </Specimen>
            </div>
        </div>
    );
}
