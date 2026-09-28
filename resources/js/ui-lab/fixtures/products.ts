import type { ProductCardData } from '@/types/catalog';
import { figurePlaceholder } from './placeholder';
import { daysAgo, hoursAgo, hoursFromNow, minutesAgo } from './time';

/** Spreads readings evenly over the last spanHours, newest a couple of minutes ago. */
function history(stocks: number[], spanHours: number) {
    return stocks.map((stock, i) => ({
        stock,
        checked_at: minutesAgo(2 + ((stocks.length - 1 - i) / (stocks.length - 1)) * spanHours * 60),
    }));
}

// Fictional products shaped like the products table, covering every card state.
// SYNC: follows types/catalog.ts, which mirrors app/Models/Product.php (see app/Models/README.md).
type Sample = Omit<ProductCardData, 'image_url' | 'currency'> & { colors: [string, string] };

const samples: Sample[] = [
    {
        id: '0f6c1a52-0001-4a4b-9a0e-000000000001', name: 'Mochi Bun Sweet Dreams Series Figures', slug: 'mochi-bun-sweet-dreams', category_name: 'Figures', skus: [{ id: 'sku-mbs-01', name: 'Cloud Nap', sku_code: 'PM-MBS-01', bar_code: '6941448600007', is_secret: false }, { id: 'sku-mbs-02', name: 'Star Pillow', sku_code: 'PM-MBS-02', bar_code: '6941448600014', is_secret: false }, { id: 'sku-mbs-03', name: 'Moon Blanket', sku_code: 'PM-MBS-03', bar_code: '6941448600021', is_secret: false }, { id: 'sku-mbs-04', name: 'Dream Catcher', sku_code: 'PM-MBS-04', bar_code: '6941448600028', is_secret: false }, { id: 'sku-mbs-05', name: 'Midnight Dreamer', sku_code: 'PM-MBS-S', bar_code: '6941448600035', is_secret: true }], last_change: { type: 'listed', occurred_at: daysAgo(1) }, first_seen_at: daysAgo(1), sell_rate_per_hour: null,
        theme_name: 'Mochi Bun', price: 5580, business_type: 'shop', spec_type: 'blind_box', sale_start_at: hoursFromNow(91),
        remain_stock: null, warehouse: 'local', is_new: true, is_sold_out: false, is_coming_soon: true,
        last_seen_at: minutesAgo(4), last_changed_at: daysAgo(1), colors: ['#f7d9c4', '#e85d5d'],
    },
    {
        id: '0f6c1a52-0001-4a4b-9a0e-000000000002', name: 'Pebble Pals Tiny Garden Plush Pendant', slug: 'pebble-pals-tiny-garden', category_name: 'Plush', skus: [{ id: 'sku-ptg-01', name: 'Sprout', sku_code: 'PM-PTG-01', bar_code: '6941448600042', is_secret: false }, { id: 'sku-ptg-02', name: 'Watering Can', sku_code: 'PM-PTG-02', bar_code: '6941448600049', is_secret: false }, { id: 'sku-ptg-03', name: 'Snail Mail', sku_code: 'PM-PTG-03', bar_code: '6941448600056', is_secret: false }, { id: 'sku-ptg-04', name: 'Rainbow Bloom', sku_code: 'PM-PTG-S', bar_code: '6941448600063', is_secret: true }], last_change: { type: 'listed', occurred_at: hoursAgo(20) }, first_seen_at: hoursAgo(20), sell_rate_per_hour: null,
        theme_name: 'Pebble Pals', price: 1680, business_type: 'draw', spec_type: 'blind_box', sale_start_at: hoursFromNow(5.3),
        remain_stock: null, warehouse: 'cross_border', is_new: true, is_sold_out: false, is_coming_soon: true,
        last_seen_at: minutesAgo(4), last_changed_at: hoursAgo(20), colors: ['#ffe39c', '#f59e0b'],
    },
    {
        id: '0f6c1a52-0001-4a4b-9a0e-000000000003', name: 'Lumi Starlight Voyage Action Figure', slug: 'lumi-starlight-voyage', category_name: 'Figures', skus: [{ id: 'sku-lsv-01', name: 'Starlight Voyager', sku_code: 'PM-LSV-01', bar_code: '6941448600070', is_secret: false }], last_change: { type: 'low_stock', occurred_at: minutesAgo(2), from: 12, to: 8 }, first_seen_at: daysAgo(4), sell_rate_per_hour: 14,
        theme_name: 'Lumi', price: 13080, business_type: 'shop', spec_type: 'normal', sale_start_at: daysAgo(3),
        remain_stock: 8, warehouse: 'local', is_new: true, is_sold_out: false, is_coming_soon: false,
        stock_history: history([120, 104, 88, 61, 47, 30, 19, 12, 8], 72), last_seen_at: minutesAgo(2), last_changed_at: minutesAgo(2),
        colors: ['#e8e1f5', '#6d5bd0'],
    },
    {
        id: '0f6c1a52-0001-4a4b-9a0e-000000000004', name: 'Kip the Fox Rainy Day Series Figures', slug: 'kip-rainy-day', category_name: 'Figures', skus: [{ id: 'sku-krd-01', name: 'Umbrella Kip', sku_code: 'PM-KRD-01', bar_code: '6941448600077', is_secret: false }, { id: 'sku-krd-02', name: 'Puddle Jump', sku_code: 'PM-KRD-02', bar_code: '6941448600084', is_secret: false }, { id: 'sku-krd-03', name: 'Raincoat', sku_code: 'PM-KRD-03', bar_code: '6941448600091', is_secret: false }, { id: 'sku-krd-04', name: 'Rainbow Tail', sku_code: 'PM-KRD-04', bar_code: '6941448600098', is_secret: false }, { id: 'sku-krd-05', name: 'Cloudy', sku_code: 'PM-KRD-05', bar_code: '6941448600105', is_secret: false }, { id: 'sku-krd-06', name: 'Thunder Kip', sku_code: 'PM-KRD-S', bar_code: '6941448600112', is_secret: true }], last_change: { type: 'price_drop', occurred_at: hoursAgo(1), from: 4980, to: 4480 }, first_seen_at: daysAgo(4), sell_rate_per_hour: 6,
        theme_name: 'Kip the Fox', price: 4480, previous_price: 4980, business_type: 'shop', spec_type: 'blind_box', sale_start_at: daysAgo(3),
        remain_stock: 142, warehouse: 'local', is_new: true, is_sold_out: false, is_coming_soon: false,
        stock_history: history([200, 188, 171, 160, 158, 150, 146, 142], 72), last_seen_at: minutesAgo(2), last_changed_at: hoursAgo(1),
        colors: ['#ffd7b0', '#ea6a1f'],
    },
    {
        id: '0f6c1a52-0001-4a4b-9a0e-000000000005', name: 'Mochi Bun Life Is a Show Mini Figure', slug: 'mochi-bun-life-is-a-show', category_name: 'Figures', skus: [{ id: 'sku-mls-01', name: 'Showtime Mochi', sku_code: 'PM-MLS-01', bar_code: '6941448600119', is_secret: false }], last_change: { type: 'sold_out', occurred_at: hoursAgo(6), from: 3, to: 0 }, first_seen_at: daysAgo(4), sell_rate_per_hour: null,
        theme_name: 'Mochi Bun', price: 7080, business_type: 'shop', spec_type: 'normal', sale_start_at: daysAgo(3),
        remain_stock: 0, warehouse: 'local', is_new: false, is_sold_out: true, is_coming_soon: false,
        stock_history: history([60, 41, 22, 9, 3, 0, 0], 30), last_seen_at: minutesAgo(3), last_changed_at: hoursAgo(6),
        colors: ['#f7d9c4', '#8b4a2b'],
    },
    {
        id: '0f6c1a52-0001-4a4b-9a0e-000000000006', name: 'Pebble Pals Midnight Snack Series', slug: 'pebble-pals-midnight-snack', category_name: 'Figures', skus: [{ id: 'sku-pms-01', name: 'Midnight Noodles', sku_code: 'PM-PMS-01', bar_code: '6941448600126', is_secret: false }, { id: 'sku-pms-02', name: 'Sleepy Toast', sku_code: 'PM-PMS-02', bar_code: '6941448600133', is_secret: false }, { id: 'sku-pms-03', name: 'Fridge Raid', sku_code: 'PM-PMS-03', bar_code: '6941448600140', is_secret: false }, { id: 'sku-pms-04', name: 'Pillow Fort', sku_code: 'PM-PMS-04', bar_code: '6941448600147', is_secret: false }, { id: 'sku-pms-05', name: 'Night Owl', sku_code: 'PM-PMS-05', bar_code: '6941448600154', is_secret: false }, { id: 'sku-pms-06', name: 'Golden Moon Snack', sku_code: 'PM-PMS-S', bar_code: '6941448600161', is_secret: true }], last_change: { type: 'sale_started', occurred_at: hoursAgo(3) }, first_seen_at: daysAgo(9), sell_rate_per_hour: null,
        theme_name: 'Pebble Pals', price: 5580, business_type: 'draw', spec_type: 'blind_box', sale_start_at: daysAgo(8),
        remain_stock: null, warehouse: 'local', is_new: false, is_sold_out: false, is_coming_soon: false,
        last_seen_at: minutesAgo(1), last_changed_at: hoursAgo(3), colors: ['#cfe8dc', '#2f8f6a'],
    },
    {
        id: '0f6c1a52-0001-4a4b-9a0e-000000000007', name: 'Lumi Frosted Orchard Vinyl Plush Hanging Card', slug: 'lumi-frosted-orchard', category_name: 'Plush', skus: [{ id: 'sku-lfo-01', name: 'Frost Apple', sku_code: 'PM-LFO-01', bar_code: '6941448600168', is_secret: false }, { id: 'sku-lfo-02', name: 'Pear Drop', sku_code: 'PM-LFO-02', bar_code: '6941448600175', is_secret: false }, { id: 'sku-lfo-03', name: 'Snow Plum', sku_code: 'PM-LFO-03', bar_code: '6941448600182', is_secret: false }, { id: 'sku-lfo-04', name: 'Crystal Cherry', sku_code: 'PM-LFO-S', bar_code: '6941448600189', is_secret: true }], last_change: { type: 'restocked', occurred_at: minutesAgo(38), from: 0, to: 48 }, first_seen_at: daysAgo(11), sell_rate_per_hour: 9,
        theme_name: 'Lumi', price: 8980, business_type: 'shop', spec_type: 'blind_box', sale_start_at: daysAgo(10),
        remain_stock: 36, warehouse: 'cross_border', is_new: false, is_sold_out: false, is_coming_soon: false,
        stock_history: history([0, 0, 0, 0, 48, 44, 40, 36], 6), last_seen_at: minutesAgo(5), last_changed_at: minutesAgo(38),
        colors: ['#dbe7f7', '#3b6fb6'],
    },
    {
        id: '0f6c1a52-0001-4a4b-9a0e-000000000008', name: 'Kip the Fox 400% Collector Edition', slug: 'kip-400-collector', category_name: 'MEGA', skus: [{ id: 'sku-k400-01', name: 'Kip 400% Collector', sku_code: 'PM-K400-01', bar_code: '6941448600196', is_secret: false }], last_change: { type: 'price_rise', occurred_at: hoursAgo(9), from: 42900, to: 45900 }, first_seen_at: daysAgo(17), sell_rate_per_hour: 1,
        theme_name: 'Kip the Fox', price: 45900, previous_price: 42900, business_type: 'shop', spec_type: 'normal', sale_start_at: daysAgo(16),
        remain_stock: 3, warehouse: 'local', is_new: false, is_sold_out: false, is_coming_soon: false,
        stock_history: history([15, 12, 10, 9, 7, 5, 3], 96), last_seen_at: minutesAgo(6), last_changed_at: hoursAgo(9),
        colors: ['#ffd7b0', '#1f1f1f'],
    },
    {
        id: '0f6c1a52-0001-4a4b-9a0e-000000000009', name: 'Moss & Mallow Picnic Club Series Figures', slug: 'moss-mallow-picnic-club', category_name: 'Figures', skus: [{ id: 'sku-mmp-01', name: 'Picnic Basket', sku_code: 'PM-MMP-01', bar_code: '6941448600203', is_secret: false }, { id: 'sku-mmp-02', name: 'Lemonade', sku_code: 'PM-MMP-02', bar_code: '6941448600210', is_secret: false }, { id: 'sku-mmp-03', name: 'Sandwich Stack', sku_code: 'PM-MMP-03', bar_code: '6941448600217', is_secret: false }], last_change: { type: 'low_stock', occurred_at: daysAgo(2), from: 58, to: 56 }, first_seen_at: daysAgo(24), sell_rate_per_hour: 2,
        theme_name: null, price: 4980, business_type: 'shop', spec_type: 'blind_box', sale_start_at: daysAgo(23),
        remain_stock: 56, warehouse: 'local', is_new: false, is_sold_out: false, is_coming_soon: false,
        stock_history: history([80, 74, 70, 66, 61, 58, 56], 120), last_seen_at: minutesAgo(7), last_changed_at: daysAgo(2),
        colors: ['#f3e9c6', '#7a9a3a'],
    },
    {
        id: '0f6c1a52-0001-4a4b-9a0e-000000000010', name: 'Pebble Pals Cloud Nine Keychain', slug: 'pebble-pals-cloud-nine', category_name: 'Accessories', skus: [{ id: 'sku-pcn-01', name: 'Cloud Nine Keychain', sku_code: 'PM-PCN-01', bar_code: '6941448600224', is_secret: false }], last_change: { type: 'sold_out', occurred_at: daysAgo(4), from: 11, to: 0 }, first_seen_at: daysAgo(31), sell_rate_per_hour: null,
        theme_name: 'Pebble Pals', price: 3990, business_type: 'shop', spec_type: 'normal', sale_start_at: daysAgo(30),
        remain_stock: null, warehouse: 'local', is_new: false, is_sold_out: true, is_coming_soon: false,
        last_seen_at: minutesAgo(8), last_changed_at: daysAgo(4), colors: ['#e9e4ff', '#c04fa0'],
    },
];

export const sampleProducts: ProductCardData[] = samples.map(({ colors, ...product }) => ({
    ...product,
    currency: 'MYR',
    image_url: figurePlaceholder(...colors),
}));

export function productBySlug(slug: string): ProductCardData {
    const product = sampleProducts.find((p) => p.slug === slug);
    if (!product) throw new Error(`No sample product with slug "${slug}"`);
    return product;
}

/** Products the sample user has alerts on (stand-in for their wishlist_items rows). */
export const sampleWatchedIds = [
    'mochi-bun-sweet-dreams',
    'lumi-starlight-voyage',
    'mochi-bun-life-is-a-show',
    'lumi-frosted-orchard',
    'kip-400-collector',
].map((slug) => productBySlug(slug).id);

/** Products the sample user pinned to their dashboard (stand-in for pinned_items rows). */
export const samplePinnedIds = [
    'lumi-starlight-voyage',
    'mochi-bun-life-is-a-show',
    'kip-rainy-day',
    'lumi-frosted-orchard',
    'kip-400-collector',
    'mochi-bun-sweet-dreams',
].map((slug) => productBySlug(slug).id);
