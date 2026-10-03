import type { AuthState } from '@/lib/capabilities';
import type { LuckyPoints } from './account';
import type { AlertSettings } from './alerts';
import type { AreaCode, CollectionSummary, ProductCardData, ProductEvent } from './catalog';

/**
 * Inertia props. SYNC: App\Http\Middleware\HandleInertiaRequests (shared) and
 * App\Http\Controllers\PageController (per page).
 */

/** The user's pins (dashboard) and watches (alerts): ListController::listsFor. */
export interface UserLists {
    pinned_product_ids: string[];
    pinned_collection_ids: string[];
    watched_product_ids: string[];
    watched_collection_ids: string[];
}

/** Every page gets these. */
export interface SharedProps {
    auth: AuthState;
    lists: UserLists;
    unread_alerts: number;
    area: AreaCode;
    [key: string]: unknown;
}

export interface DashboardProps extends SharedProps {
    products: ProductCardData[];
    collections: CollectionSummary[];
    pinned_collections: CollectionSummary[];
    events: ProductEvent[];
    lucky_points: LuckyPoints | null;
}

export interface ProductListProps extends SharedProps {
    products: ProductCardData[];
}

export interface AlertsPageProps extends SharedProps {
    settings: AlertSettings;
}
