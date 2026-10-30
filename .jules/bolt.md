
## 2023-11-20 - Optimize append-only table queries to fix hydration bottleneck
**Learning:** When querying append-only tables (like `product_events` and `stock_snapshots`), fetching all historical rows and filtering in PHP (e.g. `->get()->unique(...)` or overwriting in a foreach loop) causes massive memory spikes and database hydration bottlenecks.
**Action:** Use a `MAX(id)` subquery grouped by the foreign key (e.g. `product_id` or `sku_id`) combined with `whereIn('id', $subquery)` to let the database handle the filtering and only return the latest relevant rows.
