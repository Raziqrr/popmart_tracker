## 2026-10-31 - [Optimize Product Events Query]
**Learning:** Using `->get()->unique(...)` on append-only tables (e.g. `product_events` and `stock_snapshots`) causes extreme memory usage and database hydration by pulling thousands of rows when only a few are needed.
**Action:** When querying the latest entry for unique IDs in these append-only tables, use a `MAX(id)` subquery with `whereIn` to let the database filter for the latest distinct entries directly.
