import type { ComponentType } from 'react';
import { CalendarLab } from './modules/CalendarLab';
import { DashboardLab } from './modules/DashboardLab';
import { LayoutLab } from './modules/LayoutLab';
import { LockLab } from './modules/LockLab';
import { ProductCardLab } from './modules/ProductCardLab';
import { ProductListLab } from './modules/ProductListLab';
import { AutoLockPage } from './pages/AutoLockPage';
import { AutoLockSetupPage } from './pages/AutoLockSetupPage';
import { CatalogPage } from './pages/CatalogPage';
import { DashboardPage } from './pages/DashboardPage';
import { DropsPage } from './pages/DropsPage';
import { WatchlistPage } from './pages/WatchlistPage';

export interface LabEntry {
    id: string;
    title: string;
    /** Pages render full-bleed (the whole mock site); modules render inside the lab's content column. */
    kind: 'page' | 'module';
    component: ComponentType;
}

// Add new pages/modules here to get them into the sidebar.
export const labEntries: LabEntry[] = [
    { id: 'page-dashboard', title: 'Dashboard', kind: 'page', component: DashboardPage },
    { id: 'page-watchlist', title: 'Watchlist', kind: 'page', component: WatchlistPage },
    { id: 'page-drops', title: 'Drops', kind: 'page', component: DropsPage },
    { id: 'page-catalog', title: 'Catalog', kind: 'page', component: CatalogPage },
    { id: 'page-auto-lock', title: 'Auto-lock', kind: 'page', component: AutoLockPage },
    { id: 'page-auto-lock-setup', title: 'Auto-lock setup', kind: 'page', component: AutoLockSetupPage },

    { id: 'module-layout', title: 'Layout', kind: 'module', component: LayoutLab },
    { id: 'module-product-card', title: 'Product card', kind: 'module', component: ProductCardLab },
    { id: 'module-product-lists', title: 'Product lists', kind: 'module', component: ProductListLab },
    { id: 'module-dashboard', title: 'Dashboard widgets', kind: 'module', component: DashboardLab },
    { id: 'module-calendar', title: 'Calendar & timeline', kind: 'module', component: CalendarLab },
    { id: 'module-lock', title: 'Auto-lock', kind: 'module', component: LockLab },
];

/** Planned modules, listed in the sidebar so the lab doubles as a build checklist. */
export const plannedEntries: { title: string; kind: LabEntry['kind'] }[] = [
    { title: 'Product detail', kind: 'page' },
    { title: 'Collection detail', kind: 'page' },
    { title: 'POP NOW', kind: 'page' },
    { title: 'Store finder', kind: 'page' },
    { title: 'Alerts inbox', kind: 'page' },
    { title: 'Stock history chart', kind: 'module' },
    { title: 'SKU / figure list', kind: 'module' },
    { title: 'POP NOW box grid', kind: 'module' },
    { title: 'Store card & map', kind: 'module' },
];
