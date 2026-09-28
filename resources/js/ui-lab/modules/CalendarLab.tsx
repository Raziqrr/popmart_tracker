import { DropTile } from '@/components/drops/DropTile';
import { ReleaseCalendar } from '@/components/drops/ReleaseCalendar';
import { ReleaseTimeline } from '@/components/drops/ReleaseTimeline';
import type { ProductCardData } from '@/types/catalog';
import { productBySlug, sampleProducts, sampleWatchedIds } from '../fixtures/products';
import { hoursFromNow } from '../fixtures/time';
import { useProductActions } from '../fixtures/useProductActions';
import { LabHeader, Specimen, SpecimenGrid } from '../Specimen';

// Extra same-day drops to exercise the "+N more" overflow on one tile.
const busyDay: ProductCardData[] = [1, 2, 3, 4].map((n) => ({
    ...sampleProducts[n],
    id: `busy-${n}`,
    sale_start_at: hoursFromNow(91 + n * 0.25),
}));

export function CalendarLab() {
    const actions = useProductActions([], sampleWatchedIds);
    const month = new Date(hoursFromNow(91));
    const drop = productBySlug('pebble-pals-midnight-snack');
    const dropProps = { productHref: actions.productHref, watchedIds: actions.watchedIds, onToggleWatch: actions.onToggleWatch };

    return (
        <div className="flex flex-col gap-12">
            <LabHeader
                title="Release calendar & timeline"
                description="Drops by sale_start_at as a month calendar or a horizontal timeline. Double-click a drop (or use its bell) to toggle a reminder; today is a full-width red bar."
                source="components/drops/"
            />

            <section className="flex flex-col gap-6">
                <h2 className="text-lg font-bold">Drop tile</h2>
                <SpecimenGrid>
                    <Specimen label="Compact" note="Calendar day boxes">
                        <DropTile product={drop} href="#" watching={false} onToggleWatch={() => {}} />
                    </Specimen>
                    <Specimen label="Compact, reminder set">
                        <DropTile product={drop} href="#" watching onToggleWatch={() => {}} />
                    </Specimen>
                    <Specimen label="Large" note="Timeline columns">
                        <DropTile product={drop} href="#" watching={false} onToggleWatch={() => {}} variant="large" />
                    </Specimen>
                    <Specimen label="Large, reminder set">
                        <DropTile product={drop} href="#" watching onToggleWatch={() => {}} variant="large" />
                    </Specimen>
                </SpecimenGrid>
            </section>

            <Specimen label="Timeline" note="Opens on today; empty days collapse to a strip">
                <ReleaseTimeline products={[...sampleProducts, ...busyDay]} {...dropProps} />
            </Specimen>

            <Specimen label="Calendar: busy month" note="One day has 5 drops → '+2 more'">
                <ReleaseCalendar products={[...sampleProducts, ...busyDay]} initialMonth={month} {...dropProps} />
            </Specimen>

            <Specimen label="Calendar: empty month">
                <ReleaseCalendar products={[]} initialMonth={new Date(2026, 0, 1)} />
            </Specimen>
        </div>
    );
}
