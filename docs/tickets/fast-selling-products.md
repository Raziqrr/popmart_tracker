# Ticket: Fast-selling products (sell-rate logic)

**Status:** open · **Area:** backend (scraper/snapshots) + dashboard · **Blocks:** "Selling fast" panel and "Fastest seller" stat

## Why

The dashboard ranks products by how fast they're selling so users can react before something sells out. The UI is built (`components/dashboard/FastSellers.tsx`, "Fastest seller" tile) but reads `sell_rate_per_hour` from fixture data. Nothing computes it yet, and the UI labels it "estimate" until this lands.

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

- [ ] Rate computed and stored/refreshed on a schedule
- [ ] `sell_rate_per_hour` included in the dashboard/catalog payloads
- [ ] Feature test with a snapshot sequence including a restock in the middle
- [ ] "estimate" badge and the info note removed from `FastSellers.tsx` / the stat tile
- [ ] `types/catalog.ts` doc comment and `app/Models/README.md` sync table updated
