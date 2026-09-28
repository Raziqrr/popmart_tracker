import { useState, type ReactNode } from 'react';
import { SiteFooter } from '@/components/layout/SiteFooter';
import { SiteHeader } from '@/components/layout/SiteHeader';
import type { AreaCode } from '@/types/catalog';

/** Header + footer chrome for page mockups; stands in for the future Inertia layout. */
export function MockSiteFrame({
    currentHref,
    pinnedCount = 6,
    children,
}: {
    currentHref?: string;
    pinnedCount?: number;
    children: ReactNode;
}) {
    const [area, setArea] = useState<AreaCode>('MY');

    return (
        <div className="bg-white">
            <SiteHeader area={area} onAreaChange={setArea} currentHref={currentHref} unreadAlerts={2} pinnedCount={pinnedCount} />
            <main className="mx-auto flex max-w-7xl flex-col gap-10 px-4 pt-6 sm:px-8">{children}</main>
            <SiteFooter />
        </div>
    );
}
