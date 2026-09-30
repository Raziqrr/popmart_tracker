# Popmart Tracker

A tracker for Pop Mart: stock, restocks, prices and new drops across regions (MY, SG, TH), plus tools for POP NOW, Pop Mart's online blind-box draw: live box grids, odds per box and figure, and (planned) auto-lock.

> **Status: early.** The data model, the Pop Mart API client, box predictions and the browser extension are in place. The React frontend exists as components and page mockups in a UI lab, not yet wired to Laravel. This README grows as the project does.

## Stack

- **Backend:** Laravel 13 (PHP 8.3+), MySQL/MariaDB (the migrations use generated columns and spatial/full-text indexes, so SQLite won't work), Pest.
- **Frontend:** React 19 + TypeScript, Tailwind CSS v4, Lucide icons, built with Vite. Inertia is planned for connecting it to Laravel.
- **Browser extension:** Manifest V3, in `extension/`.

## Getting started

### Frontend only (no PHP needed)

```bash
npm install
npm run ui-lab        # http://localhost:5174 — page mockups and per-module state sheets
npm run typecheck
```

### Full app

```bash
composer run setup    # composer install, .env, key, migrations, npm build
composer run dev
```

Set the `DB_*` values in `.env` first (database `popmart_tracker`). `POPMART_API_BASE_URL` points at Pop Mart's API.

## What's where

| Path | What |
| --- | --- |
| `app/Models`, `database/migrations` | Catalog (themes, collections, products, SKUs, stores), stock/product snapshots, Pop Mart accounts and orders, pins/wishlist, POP NOW sets/boxes/hints/reveals |
| `app/Services/Api` | Pop Mart API client: one class per endpoint (`ec`, `draw`, `activity`, `store`, `storePick`, `cms`, auth). Base URLs in `config/apis.php` |
| `app/Services/Analytics` | `ExclusionBoxPredictor`: exact odds per POP NOW box (see below) |
| `app/Services/PopMartTaskClaimer.php` | Claims rewards for POP NOW daily tasks Pop Mart has already marked done (never the "buy" task) |
| `routes/api.php` | `connect` (from the extension), `claim-tasks`, set predictions, product box ranking |
| `extension/` | Captures a user's Pop Mart session after they log in on Pop Mart's own page. See `extension/README.md` |
| `resources/js/components` | React components: products, dashboard, drops (calendar/timeline), POP NOW, auto-lock, layout |
| `resources/js/ui-lab` | Vite-only lab: page mockups (`pages/`), module state sheets (`modules/`), sample data (`fixtures/`) |
| `docs/tickets` | Backend work the UI already expects: auto-lock, lucky points, fast-selling products |

## POP NOW odds in one paragraph

Pop Mart's tip card only ever tells you what a box is **not**, so every row in `pop_now_box_hints` is an exclusion. Every set seen so far is one of each non-secret figure. `ExclusionBoxPredictor` counts every valid way to give each unopened box a different figure (respecting exclusions and removing revealed figures) to get an exact chance per box and figure. A box left with one option is "confirmed by elimination". Secrets have no odds, because nothing says which box is the secret. When a set does contain the secret, it takes one normal figure's place, so that set's odds are slightly overconfident.

- **Per set:** `GET /api/pop-now-sets/{set}/predictions`.
- **Per product:** `GET /api/products/{product}/box-ranking?sku_id=` for the best box for a figure across sets.
- **In the lab:** `resources/js/lib/popnow.ts` mirrors the same maths.

## Conventions

- **Frontend types mirror the models by hand.** `app/Models/README.md` lists every mirror and what to update when a model changes; `npm run typecheck` catches the rest.
- **Money** is stored in minor units with an ISO currency. **Timestamps** are ISO 8601. Pop Mart's own IDs are the primary keys.
- **Pop Mart branding stays local.** Logos in `resources/js/ui-lab/assets/popmart/` are gitignored and only for referring to Pop Mart, never as this app's logo.

## Before deploying

- [ ] User login. Account-bound API calls (anything that uses a Pop Mart session: connect, claim tasks, locks) are only available to logged-in users, for accounts they've connected. Guests get read-only data.
- [ ] `PopmartAccountRepository::connect()` upserts on `(user_id, popmart_member_id)` instead of always inserting.
- [ ] Extension: set the real domain in `manifest.json` (`api_base_url`, `externally_connectable`, `host_permissions`).
- [ ] Rate limits: Pop Mart's limits are unknown, so nothing throttles calls yet and users need to be careful. Build a rate-limit detector once we see real traffic after deploying.

## Contributing

Branch from the latest `main` on `Raziqrr/popmart_tracker`, keep `npm run typecheck` clean, and open a pull request.
