// Shapes mirror the Eloquent models' toArray() output (snake_case), so Inertia
// props can be passed straight through without mapping.
//
// SYNC: hand-written mirror of app/Models/Product.php and the products migration.
// If either side changes, update the other plus ui-lab/fixtures/products.ts.
// See app/Models/README.md.

export type AreaCode = 'MY' | 'SG' | 'TH' | (string & {});

/** Pop Mart listing tags as they appear in products.tags. */
export type ProductTag = 'new' | 'OUT_OF_STOCK' | 'COMING_SOON' | (string & {});

export interface Product {
    id: string; // Pop Mart spuId
    theme_id: string | null;
    collection_id: string | null;
    name: string;
    slug: string;
    price: number; // minor units
    currency: string; // ISO 4217
    business_type: 'shop' | 'draw';
    spec_type: string | null; // blind_box, normal...
    area_codes: AreaCode[];
    sale_start_at: string | null; // ISO 8601
    sale_end_at: string | null;
    tags: ProductTag[];
    sales: number | null;
    remain_stock: number | null;
    warehouse: string | null; // local, cross_border
    is_new: boolean;
    is_sold_out: boolean;
    is_coming_soon: boolean;
    last_seen_at: string; // last time the scraper saw this product
}

/**
 * What product cards and rows need. Everything outside the Pick is DERIVED by
 * the controller, not a products column:
 * - image_url: first SKU's main_image
 * - theme_name: themes.name
 * - previous_price: price from the latest product_snapshots row with a different price
 * - stock_history: recent stock readings with their check time, oldest first, from stock_snapshots
 * - last_changed_at: checked_at of the latest snapshot that differed from the one before
 */
export type ProductCardData = Pick<
    Product,
    | 'id'
    | 'name'
    | 'slug'
    | 'price'
    | 'currency'
    | 'business_type'
    | 'spec_type'
    | 'sale_start_at'
    | 'remain_stock'
    | 'warehouse'
    | 'is_new'
    | 'is_sold_out'
    | 'is_coming_soon'
    | 'last_seen_at'
> & {
    image_url: string | null;
    theme_name?: string | null;
    previous_price?: number | null;
    stock_history?: StockPoint[];
    last_changed_at?: string | null;
    /** The most recent detected change (latest ProductEvent for this product), shown as "what changed". */
    last_change?: ProductChange | null;
    /**
     * Units sold per hour over the last 6 h of stock drops (products.sell_rate_per_hour,
     * refreshed by popmart:sell-rates). Null for masked stock and pre-orders.
     */
    sell_rate_per_hour?: number | null;
    /** Remaining stock ÷ sell rate (products.hours_to_sell_out); null when it isn't selling. */
    hours_to_sell_out?: number | null;
    /** When the products row was created, i.e. first scraped. */
    first_seen_at?: string;
    /** Category name resolved from products.category_id (Figures, Plush, MEGA...). */
    category_name?: string | null;
    /** The product's figures, for SKU / barcode search. From the skus table. */
    skus?: SkuSummary[];
};

/** One figure (SKU) of a product, as used by search and filters. SYNC: mirrors app/Models/Sku.php. */
export interface SkuSummary {
    id: string; // Pop Mart skuId
    name: string;
    sku_code: string | null;
    bar_code: string | null;
    /** skus.box_type === 'secret'. */
    is_secret: boolean;
    /** skus.main_image. */
    image_url?: string | null;
}

/** One stock reading: stock_snapshots.stock at stock_snapshots.checked_at (summed across SKUs). */
export interface StockPoint {
    checked_at: string;
    stock: number;
}

/** Per-status product counts, keyed like lib/productStatus's ProductStatus. */
export interface StatusCounts {
    in_stock: number;
    low_stock: number;
    coming_soon: number;
    sold_out: number;
}

/**
 * A collection (one theme in one area) with its products' status rolled up.
 * SYNC: mirrors app/Models/Collection.php; everything after `slug` is DERIVED by the controller.
 */
export interface CollectionSummary {
    id: string; // Pop Mart collectionId
    /** Null when the collection was discovered without its theme (ipId). */
    theme_id: string | null;
    area: AreaCode;
    /** A placeholder like "Collection 1a2b3c4d" while name_confirmed is false. */
    name: string;
    slug: string;
    name_confirmed: boolean;
    theme_name: string | null;
    image_url: string | null;
    product_count: number;
    status_counts: StatusCounts;
    next_sale_at: string | null;
    first_seen_at: string; // collections.created_at
    last_changed_at: string | null;
}

/**
 * A detected change, as stored in product_events by the Pop Mart sync.
 * SYNC: mirrors App\Services\PopMart\Sync\ProductEventType. Release and
 * pre-order events are separate: a pre-order is buy-now-ship-later.
 */
export type ProductEventType =
    | 'restocked'
    | 'sold_out'
    | 'price_drop'
    | 'price_rise'
    | 'listed'
    | 'sale_started'
    | 'sale_time_changed'
    | 'low_stock'
    | 'preorder_opened'
    | 'preorder_closed'
    | 'tag_added'
    | 'tag_removed';

/** A change without its product: what happened, when, and the before/after values. */
export interface ProductChange {
    type: ProductEventType;
    occurred_at: string;
    /**
     * Before/after values: stock for stock events, minor-unit price for price
     * events, an ISO time for sale_time_changed, the tag for tag events.
     */
    from?: number | string | null;
    to?: number | string | null;
}

export interface ProductEvent extends ProductChange {
    id: string;
    product: ProductCardData;
}
