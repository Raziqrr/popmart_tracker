import type { ProductEventType } from './catalog';

/**
 * Alerts for watched products. SYNC: mirrors App\Notifications\ProductAlert /
 * ProductAlertDigest (toArray) and App\Services\Alerts\AlertPreferences.
 */

/** One product event in an alert. */
export interface AlertEntry {
    event_id: number;
    event_type: ProductEventType;
    product_id: string;
    product_name: string;
    title: string;
    detail: string;
    url: string;
}

export type AlertItem =
    | ({ id: string; kind: 'product'; read_at: string | null; created_at: string; image_url: string | null; occurred_at: string | null } & AlertEntry)
    | { id: string; kind: 'digest'; read_at: string | null; created_at: string; title: string; items: AlertEntry[]; occurred_at: string | null };

/** GET /api/alerts */
export interface AlertInbox {
    unread: number;
    alerts: AlertItem[];
}

/** GET / PUT /api/alert-settings */
export interface AlertSettings {
    types: ProductEventType[];
    email: boolean;
    telegram: boolean;
    telegram_chat_id: string | null;
    /** Read-only: the event types a user may pick. */
    available_types?: ProductEventType[];
    /** Read-only: whether the server has a Telegram bot configured. */
    telegram_available?: boolean;
}
