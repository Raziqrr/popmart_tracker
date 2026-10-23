<?php

namespace App\Services\PopMart\Sync;

/**
 * What changed about a product, as stored in product_events. Mirrored by
 * ProductEventType in resources/js/types/catalog.ts. Release and pre-order
 * events are kept apart: a pre-order is buy-now-ship-later (early pass),
 * a release goes on sale at a set time.
 */
enum ProductEventType: string
{
    // Release / regular stock
    case Listed = 'listed';
    case SaleStarted = 'sale_started';
    case SaleTimeChanged = 'sale_time_changed';
    case Restocked = 'restocked';
    case SoldOut = 'sold_out';
    case LowStock = 'low_stock';
    case PriceDrop = 'price_drop';
    case PriceRise = 'price_rise';

    // Pre-order
    case PreorderOpened = 'preorder_opened';
    case PreorderClosed = 'preorder_closed';

    // Raw tag changes, including tags we've never seen (how surprises show up first)
    case TagAdded = 'tag_added';
    case TagRemoved = 'tag_removed';

    /** Worth bumping the product's whole collection to the hot polling tier (drops come in clusters). */
    public function signalsActivity(): bool
    {
        return in_array($this, [self::Listed, self::SaleStarted, self::SaleTimeChanged, self::Restocked, self::PreorderOpened], true);
    }
}
