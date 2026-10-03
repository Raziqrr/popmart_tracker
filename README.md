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
| `routes/api.php` | Public, stateless reads: set predictions, product box ranking |
| `routes/web.php` (`/api/…`) | Account routes on the login session: `me`, `popmart-accounts/connect`, `claim-tasks` |
| `app/Auth` | `Capability`: who can do what (see "Who can do what" below) |
| `extension/` | Captures a user's Pop Mart session after they log in on Pop Mart's own page. See `extension/README.md` |
| `resources/js/components` | React components: products, dashboard, drops (calendar/timeline), POP NOW, auto-lock, layout |
| `resources/js/ui-lab` | Vite-only lab: page mockups (`pages/`), module state sheets (`modules/`), sample data (`fixtures/`) |
| `docs/tickets` | Backend work the UI already expects: auto-lock, lucky points, fast-selling products |

## POP NOW odds in one paragraph

Pop Mart's tip card only ever tells you what a box is **not**, so every row in `pop_now_box_hints` is an exclusion. Every set seen so far is one of each non-secret figure. `ExclusionBoxPredictor` counts every valid way to give each unopened box a different figure (respecting exclusions and removing revealed figures) to get an exact chance per box and figure. A box left with one option is "confirmed by elimination". Secrets have no odds, because nothing says which box is the secret. When a set does contain the secret, it takes one normal figure's place, so that set's odds are slightly overconfident.

- **Per set:** `GET /api/pop-now-sets/{set}/predictions`.
- **Per product:** `GET /api/products/{product}/box-ranking?sku_id=` for the best box for a figure across sets.
- **In the lab:** `resources/js/lib/popnow.ts` mirrors the same maths.

## Data sync

The server keeps its own copy of Pop Mart's data and pages read only from it. Code: `app/Services/PopMart/Sync`, commands in `routes/console.php`.

| Command | Schedule | What it does |
| --- | --- | --- |
| `popmart:sweep` | every 3 min | Discovery: every known collection's listings (least recently swept first), plus any product ID in Pop Mart's live CMS content (so unannounced drops and pre-orders show up), plus details for products without SKUs. Finds new collections along the way |
| `popmart:add-collection <id>` | by hand | Adds a collection by its Pop Mart id and sweeps it straight away |
| `popmart:sync-stock` | every minute | Stock for products that are due, by tier: **hot** (pinned/watched, pre-order, sale within 2 h, recent activity in its collection) every minute, **normal** every 20 min, **cold** (sold out and quiet, or delisted) daily |
| `popmart:poll-popnow` | every minute | Live POP NOW grids via the **house account** (`POPMART_HOUSE_ACCOUNT_ID`), for draws someone is viewing, watching or that are hot |
| `popmart:prune-calls` | daily | Keeps 7 days of the call log |
| `popmart:status` | by hand | Calls and 429s in the last hour, pause, house account, due products, new response fields |
| `popmart:capture` | by hand | Calls one endpoint, saves the raw response, `--check` runs its adapter over it |

- **One budget.** Every call goes through `PopMartThrottle`: `POPMART_CALLS_PER_MINUTE` (60) shared, background sync capped at `POPMART_BACKGROUND_PER_MINUTE` (40) so user actions always have room. A 429 from Pop Mart pauses everything. Every call is logged in `popmart_calls`.
- **New collections.** There's no known "all collections" endpoint, so `CollectionDiscovery` learns them from the `collectionId` / `ipId` in product payloads, by probing CMS IDs that aren't products (a few per sweep: a collection's listing returns products), and from `popmart:add-collection`. Without a name from Pop Mart a collection gets a placeholder (`name_confirmed = false`) until a payload carries the real one.
- **Changes become events.** `ProductChangeDetector` writes `product_events`: `listed`, `sale_started`, `sale_time_changed`, `restocked`, `sold_out`, `low_stock`, price moves, `preorder_opened` / `preorder_closed` (kept apart from releases) and raw tag changes. Stock snapshots are only written when stock changes; `skus.last_checked_at` records every check.
- **The house account only watches.** `AssignSet` releases any boxes a session holds, so background grid polling only uses the house account, and endpoints marked `HoldsBoxes` refuse to run with it. A logged-in viewer can refresh one set with their own session through `POST /api/pop-now-sets/{set}/refresh`, which only uses the read-only `CheckSetBoxLock`.
- **Unconfirmed field names.** Pop Mart field names live in one adapter per endpoint (`Sync/Adapters`). Only `PopNowSetAdapter` is confirmed. Before trusting the catalog and stock sync, run `popmart:capture <Endpoint> '<payload>' --check` for each listing endpoint and `CheckSkuStock`, fix any names it reports, and set `CONFIRMED = true`.
- **Learning Pop Mart's schema.** Every field path in every response is recorded in `popmart_payload_fields`; `popmart:status` lists new ones. That's how the pre-order marker and early-pass state will be found (`SpuAdapter::saleMode()` returns `regular` until then).

## Who can do what

There are three access levels: **guest** (browse, POP NOW odds), **member** (logged in with Fortify: watchlist, connect Pop Mart) and **Pop Mart** (logged in *and* a connected Pop Mart account with a live session: claim tasks, redeem, lock, auto-lock, checkout).

- **One list:** `app/Auth/Capability.php` says what each action needs. To gate a route, add `->middleware('capability:<name>')`. Account routes go in `routes/web.php` under `/api` so they get the login session and CSRF.
- **Ownership:** when a route takes `{popmartAccount}`, `PopmartAccountPolicy` makes sure it's the user's own account, and an expired session answers `409 popmart_session_expired`.
- **Errors** come back as `{ error: { code, message } }`: `unauthenticated` (401), `forbidden` (403) and `popmart_not_connected` (409).
- **Frontend:** `GET /api/me` returns the user, their Pop Mart connection and allowed capabilities. `resources/js/lib/capabilities.ts` mirrors the list; wrap gated actions in `<RequiresCapability capability="…">` to show "Log in" / "Connect Pop Mart" instead.
- `tests/Feature/CapabilityTest.php` fails if an account route has no capability or the PHP and JS lists drift apart.

## Conventions

- **Frontend types mirror the models by hand.** `app/Models/README.md` lists every mirror and what to update when a model changes; `npm run typecheck` catches the rest.
- **Money** is stored in minor units with an ISO currency. **Timestamps** are ISO 8601. Pop Mart's own IDs are the primary keys.
- **Pop Mart branding stays local.** Logos in `resources/js/ui-lab/assets/popmart/` are gitignored and only for referring to Pop Mart, never as this app's logo.
- **Pop Mart failures are one exception.** Every endpoint throws `PopMartException` (session expired, rate limited, unreachable, rejected, unexpected response), which our API returns as `{ error: { code, message, retry_after } }`. Run account calls through `PopmartAccount::usingSession()` so an expired session gets marked on the account. On the frontend, `lib/api.ts` turns these into `ApiError`, and `ApiErrorNotice` shows them.

## Before deploying

- [x] User login (Fortify) and capability checks on account routes. Still to do: login and register pages (Fortify runs headless for now, so `/login` and `/register` only take JSON or form posts).
- [x] `PopmartAccountRepository::connect()` upserts on `(user_id, popmart_member_id)` instead of always inserting. Connect now checks the cookie with Pop Mart's `get-session` first and rejects one Pop Mart doesn't accept (422).
- [ ] Extension: set the real domain in `manifest.json` (`api_base_url`, `externally_connectable`, `host_permissions`).
- [ ] Confirm the sync adapters with `popmart:capture --check` (see "Data sync"), connect the house account and set `POPMART_HOUSE_ACCOUNT_ID`, and run the scheduler (`php artisan schedule:work` locally, a cron entry in production).
- [ ] Rate limits: Pop Mart's limits are unknown, so nothing throttles calls yet and users need to be careful. A 429 from Pop Mart now surfaces as `popmart_rate_limited` (with `Retry-After` when sent); build throttling once we see real traffic after deploying.

## Contributing

Branch from the latest `main` on `Raziqrr/popmart_tracker`, keep `npm run typecheck` clean, and open a pull request.
