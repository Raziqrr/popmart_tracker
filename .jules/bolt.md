
## 2023-11-20 - Subqueries over PHP-side filtering for historical records
**Learning:** Using `->get()->unique(...)` or fetching an entire history (e.g. `->where('checked_at', '<', $since)->get()`) merely to find the most recent matching record per entity causes excessive database hydration and memory usage, especially for append-only tables (like `product_events` and `stock_snapshots`).
**Action:** Always favor a `MAX(id)` grouping subquery (e.g. `selectRaw('MAX(id) as id')->groupBy(...)`) combined with a `whereIn('id', $subquery)` to restrict the result set directly in the database engine, vastly reducing the amount of data transferred and processed by PHP.
