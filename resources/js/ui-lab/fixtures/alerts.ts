import type { AlertEntry, AlertItem, AlertSettings } from '@/types/alerts';
import type { ProductEventType } from '@/types/catalog';
import { hoursAgo, minutesAgo } from './time';

// SYNC: follows AlertItem / AlertSettings in types/alerts.ts (App\Notifications\ProductAlert, AlertPreferences).

const entry = (event_id: number, event_type: ProductEventType, product_name: string, title: string, detail: string): AlertEntry => ({
    event_id,
    event_type,
    product_id: `p-${event_id}`,
    product_name,
    title,
    detail,
    url: '#',
});

export const sampleAlerts: AlertItem[] = [
    {
        id: 'a1', kind: 'product', read_at: null, created_at: minutesAgo(3), occurred_at: minutesAgo(3), image_url: null,
        ...entry(101, 'restocked', 'Lumi Frosted Orchard Vinyl Plush Hanging Card', 'Restocked', 'Back in stock: 48 available'),
    },
    {
        id: 'a2', kind: 'product', read_at: null, created_at: minutesAgo(25), occurred_at: minutesAgo(25), image_url: null,
        ...entry(102, 'preorder_opened', 'Kip the Fox 400% Collector Edition', 'Pre-order opened', 'Buy now, ships later (early pass needed)'),
    },
    {
        id: 'a3', kind: 'digest', read_at: null, created_at: hoursAgo(2), occurred_at: hoursAgo(2),
        title: '6 updates on things you watch',
        items: [
            entry(103, 'listed', 'Pebble Pals Tiny Garden Plush Pendant', 'New listing', 'Listed at MYR 69.90'),
            entry(104, 'sale_time_changed', 'Pebble Pals Midnight Snack Series', 'Sale time set', 'Opens 5 Oct, 10:00'),
            entry(105, 'low_stock', 'Lumi Starlight Voyage Action Figure', 'Stock falling', 'Only 8 left'),
            entry(106, 'price_drop', 'Kip the Fox Rainy Day Series Figures', 'Price drop', 'MYR 49.80 → MYR 44.80'),
            entry(107, 'sale_started', 'Mochi Bun Sweet Dreams Series Figures', 'Sale opened', 'On sale now at MYR 55.80'),
            entry(108, 'restocked', 'Moss & Mallow Picnic Club Series Figures', 'Restocked', 'Back in stock: 20 available'),
        ],
    },
    {
        id: 'a4', kind: 'product', read_at: hoursAgo(20), created_at: hoursAgo(26), occurred_at: hoursAgo(26), image_url: null,
        ...entry(109, 'sold_out', 'Mochi Bun Life Is a Show Mini Figure', 'Sold out', 'No stock left'),
    },
];

export const sampleAlertSettings: AlertSettings = {
    types: ['restocked', 'sale_started', 'sale_time_changed', 'preorder_opened', 'low_stock', 'price_drop', 'listed'],
    email: false,
    telegram: false,
    telegram_chat_id: null,
    available_types: ['restocked', 'sale_started', 'sale_time_changed', 'preorder_opened', 'preorder_closed', 'low_stock', 'sold_out', 'price_drop', 'listed'],
    telegram_available: true,
};

/** Simulated PUT /api/alert-settings: echoes what was saved after a short delay. */
export const fakeSaveAlertSettings = (settings: AlertSettings) => new Promise<AlertSettings>((resolve) => setTimeout(() => resolve(settings), 700));
