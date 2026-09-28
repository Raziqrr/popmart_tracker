# UI lab

Mockups of the site's pages and a state sheet for every frontend module, running on Vite alone (no PHP/Laravel needed).

```bash
npm run ui-lab
```

Then open http://localhost:5174. Entries are hash routes (`#/page-home`, `#/module-product-card`, ...).

## Layout

| Path | What goes there |
| --- | --- |
| `pages/` | Full-page mockups (header + content + footer) built from the real components in `resources/js/components`. |
| `modules/` | One sheet per module showing every state and edge case side by side. |
| `fixtures/` | Fake data shaped like the Eloquent models (`products`, `skus`, ...), plus in-memory stand-ins such as `useWatchlist`. |
| `registry.ts` | The sidebar: add each new page/module here. `plannedEntries` lists what's still to build. |
| `Specimen.tsx` | `LabHeader`, `Specimen`, `SpecimenGrid` helpers for module sheets. |

## Keeping fixtures in sync with the backend

Fixtures follow the types in `resources/js/types/catalog.ts`, which are a **hand-written mirror** of the Eloquent models (currently `Product` and its migration). The chain is:

```
migration + app/Models/Product.php  →  types/catalog.ts  →  ui-lab/fixtures/*.ts  →  components
```

- **Backend changed** (column added/renamed/removed, cast or enum changed): update `types/catalog.ts`, then the fixtures here, then run `npm run typecheck`.
- **Frontend needs a new field**: add the column/cast on the backend first; don't invent fields that only exist in the frontend types. Derived values the controller computes (like `image_url`) belong on `ProductCardData`, documented as such.

The mapping table lives in [`app/Models/README.md`](../../../app/Models/README.md).

## Pop Mart assets (local only)

`assets/popmart/` holds Pop Mart's own logo files for mockups: `popmart-wordmark.svg` (header logo), `popmart-logo.png` (500×500) and `popmart-favicon.ico` (64×64). The folder is **gitignored**: these are Pop Mart trademarks, so they stay on your machine. On a fresh clone, re-download them from popmart.com if you need them.

Use them only to *refer to* Pop Mart (e.g. an "Open in Pop Mart" link, the connect-account screen, source attribution), never as this site's own logo.

## Rules

- Mockups import the **real** components from `@/components/...`. Don't fork a component inside the lab; fix it at the source.
- Fixture data uses fictional products and generated SVG art, never Pop Mart photos or logos.
- Every new component gets a module sheet covering its states before it's used in a page.
- This folder is dev-only: it isn't part of the Laravel Vite build (`vite.config.js`), only `vite.ui-lab.config.ts`.
