# Ticket: Fast-selling products (sell-rate logic)

**Status:** done · **Area:** backend (scraper/snapshots) + dashboard · **Blocks:** None

## Why

The dashboard ranks products by how fast they're selling so users can react before something sells out. The UI is built (`components/dashboard/FastSellers.tsx`, "Fastest seller" tile) and reads real `sell_rate_per_hour` computed by `popmart:sell-rates`.

## What to build

A per-product **sell rate** (units per hour) derived from `stock_snapshots`, exposed as `sell_rate_per_hour` on the product payload (`ProductCardData` in `resources/js/types/catalog.ts`).

### Proposed calculation

For each SKU, over a rolling window (start with 6h):

1. Take consecutive `stock_snapshots` pairs `(prev, next)` ordered by `checked_at`.
2. Count only **decreases**: `sold = max(prev.stock - next.stock, 0)`. Increases are restocks and must not cancel out sales.
3. `rate = sum(sold) / hours_between(first.checked_at, last.checked_at)`.
4. Product rate = sum of its SKUs' rates.

Also expose `hours_to_sell_out = remain_stock / rate` (the UI currently computes this client-side).

### Open questions

- **Masked stock:** `remain_stock` is only present for un-masked SPUs. For masked products we only see `has_stock` flips. Rank those separately, or exclude them?
- **Polling gaps:** if the scraper misses a window, a big drop looks like a spike. Cap per-interval rate or require a minimum number of snapshots.
- **Store vs online:** do in-store pickup stocks count? `stock_snapshots` is per SKU only today.
- **Where to compute:** a scheduled job writing to a `product_sales_rates` table (or a column on `products`) is cheaper than computing on every dashboard load.

## Done when

- [x] Rate computed and stored/refreshed on a schedule (`popmart:sell-rates`, `App\Services\PopMart\Sync\SellRate`)
- [x] `sell_rate_per_hour` included in the dashboard/catalog payloads (`ProductCards.php`)
- [x] Test with a snapshot sequence including a restock in the middle (`tests/Feature/PopMartSyncTest.php`)
- [x] "estimate" badge and the info note removed from `FastSellers.tsx` / the stat tile
- [x] `types/catalog.ts` doc comment and `app/Models/README.md` sync table updated

## Decisions taken

- **Masked stock:** no rate (null). Only `has_stock` flips are known, so there's nothing to divide.
- **Polling gaps:** stock snapshots are written only when stock changes, so the stock between two rows is exact; the window starts from the last row before it. Histories shorter than 30 minutes get no rate.
- **Store vs online:** online stock only (`stock_snapshots` is per SKU).
- **Where:** `popmart:sell-rates` every 10 minutes writes `products.sell_rate_per_hour`, `hours_to_sell_out` and `sell_rate_computed_at`. Pre-orders are skipped (their stock is a quota).
