import type { AreaCode } from '@/types/catalog';

/** Placeholder product name; the tracker shouldn't present itself under Pop Mart's brand. */
export const SITE_NAME = 'BoxRadar';

export const AREAS: { code: AreaCode; label: string }[] = [
    { code: 'MY', label: 'Malaysia' },
    { code: 'SG', label: 'Singapore' },
    { code: 'TH', label: 'Thailand' },
];

export interface NavItem {
    label: string;
    href: string;
    hasMenu?: boolean;
}

// Ordered by how often a tracker user needs them: what changed, what I watch, what's next.
export const MAIN_NAV: NavItem[] = [
    { label: 'Dashboard', href: '/' },
    { label: 'Watchlist', href: '/watchlist' },
    { label: 'Drops', href: '/drops' },
    { label: 'Catalog', href: '/catalog', hasMenu: true },
    { label: 'POP NOW', href: '/pop-now' },
    { label: 'Auto-lock', href: '/auto-lock' },
    { label: 'Stores', href: '/stores' },
];
