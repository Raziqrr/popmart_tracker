import type { ProductEvent } from '@/types/catalog';
import { productBySlug } from './products';
import { daysAgo, hoursAgo, minutesAgo } from './time';

// Sample change log, consistent with the products fixture (what the snapshot diffing would produce).
export const sampleEvents: ProductEvent[] = [
    { id: 'e1', type: 'low_stock', product: productBySlug('lumi-starlight-voyage'), occurred_at: minutesAgo(2), from: 12, to: 8 },
    { id: 'e2', type: 'restocked', product: productBySlug('lumi-frosted-orchard'), occurred_at: minutesAgo(38), from: 0, to: 48 },
    { id: 'e3', type: 'price_drop', product: productBySlug('kip-rainy-day'), occurred_at: hoursAgo(1), from: 4980, to: 4480 },
    { id: 'e4', type: 'sale_started', product: productBySlug('pebble-pals-midnight-snack'), occurred_at: hoursAgo(3) },
    { id: 'e5', type: 'sold_out', product: productBySlug('mochi-bun-life-is-a-show'), occurred_at: hoursAgo(6), from: 3, to: 0 },
    { id: 'e6', type: 'price_rise', product: productBySlug('kip-400-collector'), occurred_at: hoursAgo(9), from: 42900, to: 45900 },
    { id: 'e7', type: 'listed', product: productBySlug('pebble-pals-tiny-garden'), occurred_at: hoursAgo(20) },
    { id: 'e8', type: 'listed', product: productBySlug('mochi-bun-sweet-dreams'), occurred_at: daysAgo(1) },
    { id: 'e9', type: 'low_stock', product: productBySlug('kip-400-collector'), occurred_at: daysAgo(1.5), from: 7, to: 5 },
    { id: 'e10', type: 'sold_out', product: productBySlug('pebble-pals-cloud-nine'), occurred_at: daysAgo(4), from: 11, to: 0 },
];
