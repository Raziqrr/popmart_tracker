import { createContext, useContext } from 'react';
import type { ProductCardData } from '@/types/catalog';
import type { AutoLockRule } from '@/types/lock';

/**
 * Site-wide auto-lock entry points. Provided once by the layout so any lock
 * button, anywhere, can open setup without each page wiring it up.
 */
export interface AutoLockApi {
    /** POP NOW products with an enabled rule. */
    lockedIds: ReadonlySet<string>;
    /** Quick setup for a product (edits its rule if it has one). */
    open: (product: ProductCardData) => void;
    /** Quick setup for an existing rule. */
    openRule: (rule: AutoLockRule) => void;
    /** Search-first picker for when you're not looking at the product (header button, shortcut). */
    openFinder: () => void;
}

export const AutoLockContext = createContext<AutoLockApi | null>(null);

/** The site-wide auto-lock API, or null outside a provider (e.g. isolated previews). */
export function useAutoLockApi(): AutoLockApi | null {
    return useContext(AutoLockContext);
}
