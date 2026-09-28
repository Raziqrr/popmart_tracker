import { useState } from 'react';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { SiteFooter } from '@/components/layout/SiteFooter';
import { SiteHeader } from '@/components/layout/SiteHeader';
import type { AreaCode } from '@/types/catalog';
import { LabHeader, Specimen } from '../Specimen';

export function LayoutLab() {
    const [area, setArea] = useState<AreaCode>('MY');

    return (
        <div className="flex flex-col gap-12">
            <LabHeader
                title="Layout"
                description="Site header (search, region switcher, alerts/wishlist badges, main nav), breadcrumbs and footer. Resize the browser below 1024px to see the mobile menu."
                source="components/layout/"
            />

            <Specimen label="Header: default">
                <SiteHeader area={area} onAreaChange={setArea} />
            </Specimen>

            <Specimen label="Header: signed in with alerts" note="unreadAlerts = 3, pinnedCount = 12, current page highlighted">
                <SiteHeader area={area} onAreaChange={setArea} unreadAlerts={3} pinnedCount={12} currentHref="/watchlist" />
            </Specimen>

            <Specimen label="Breadcrumbs">
                <Breadcrumbs
                    items={[
                        { label: 'Home', href: '/' },
                        { label: 'Themes', href: '/themes' },
                        { label: 'Mochi Bun' },
                    ]}
                />
            </Specimen>

            <Specimen label="Footer">
                <SiteFooter />
            </Specimen>
        </div>
    );
}
