# Models

## Frontend mirrors — keep in sync

Some models are mirrored by hand as TypeScript types for the React frontend. There is no code generation yet, so **a change on either side needs the matching change on the other**.

| Backend (source of truth) | Frontend mirror | Also update |
| --- | --- | --- |
| `Product` + `database/migrations/*_create_products_table.php` | `Product`, `ProductCardData`, `ProductTag`, `AreaCode` in `resources/js/types/catalog.ts` | `resources/js/ui-lab/fixtures/products.ts` |
| `Collection` + `*_create_collections_table.php` | `CollectionSummary` in `resources/js/types/catalog.ts` | `resources/js/ui-lab/fixtures/collections.ts` |
| `Sku` + `*_create_skus_table.php` | `SkuSummary`, and `ProductCardData.skus` (plus `category_name`, resolved from `products.category_id`) | `skus` / `category_name` in `resources/js/ui-lab/fixtures/products.ts` |
| `StockSnapshot` / `ProductSnapshot` (diffed) | `ProductEvent` / `ProductChange`, and the derived `stock_history`, `previous_price`, `last_changed_at`, `last_change` on `ProductCardData` | `resources/js/ui-lab/fixtures/events.ts` |
| `PopNowSet`, `PopNowBox`, `PopNowBoxHint`, `PopNowBoxReveal` | `PopNowSet`, `PopNowBox`, `BoxHint`, `BoxReveal` in `resources/js/types/popnow.ts` (`BoxState` and `composition` are derived) | `resources/js/ui-lab/fixtures/popnow.ts` |
| `PinnedItem`, `WishlistItem` | `pinnedIds` / `watchedIds` in `resources/js/components/product/actions.ts` | `samplePinnedIds` / `sampleWatchedIds` in fixtures |

### Frontend fields with no backend yet

These are in the TypeScript types and UI but **nothing on the backend produces them**. Each has a ticket:

| Field / type | Ticket |
| --- | --- |
| `ProductCardData.sell_rate_per_hour` | `docs/tickets/fast-selling-products.md` |
| `LuckyPoints` (`resources/js/types/account.ts`) | `docs/tickets/lucky-points.md` |
| `AutoLockRule`, `LockAttempt`, `LockFigure`, `PopNowLockLimits` (`resources/js/types/lock.ts`) | `docs/tickets/auto-lock.md` |

### When you change a mirrored model

Adding, renaming, removing or retyping a column, a cast, a generated column (`is_new`, `is_sold_out`, `is_coming_soon`) or an enum value (`business_type`, tags):

1. Update the migration and the model (`$fillable`, `casts()`).
2. Update the type in `resources/js/types/catalog.ts`. Field names stay **snake_case**, matching `toArray()` output passed through Inertia props.
3. Update the UI-lab fixtures so every state still has sample data.
4. Run `npm run typecheck`. It flags every component and fixture using the old shape.

### Conventions the frontend relies on

- Money is stored in **minor units** (`price` = 5580 → RM55.80) with an ISO 4217 `currency`. `resources/js/lib/format.ts` does the conversion.
- Timestamps serialize as ISO 8601 strings.
- UUID primary keys are Pop Mart's own IDs (`spuId`, `skuId`, …) and are strings in TypeScript.

When a new model gets a frontend type, add a row to the table above.
