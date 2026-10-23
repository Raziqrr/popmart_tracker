# Pop Mart Tracker - Architectural Context for Google Jules

This document outlines the architecture, data models, subsystems, and workflows of the **Pop Mart Tracker** project to enable autonomous agents to navigate and modify the codebase with deep domain awareness.

---

## 1. System Overview

Pop Mart Tracker is a Laravel 12 application designed to:
1. **Track and Sync Catalogs:** Monitor Pop Mart Asia-Pacific (APAC) endpoints for product releases, blind box series, SPU/SKU inventory, and pricing.
2. **POP NOW Box Monitoring & AutoLock:** Monitor real-time online blind box draws and grids (AssignSet, CheckSetBoxLock, BoxHold), predicting excluded boxes and optionally securing reservations.
3. **Alerting System:** Dispatch stock and drop notifications to subscribers via Telegram bots, webhooks, and email digests.
4. **Agent Orchestration:** Interface with Google Jules and AI agents through structured task queues, quota throttling, and CI workflows.

---

## 2. Directory Structure & Key Subsystems

```
app/
├── Auth/                        # Role and capability gates (RequireCapability middleware)
├── Console/
│   └── Commands/
│       ├── PopMartCapture.php   # Endpoint probe and adapter verification CLI
│       ├── JulesQueueCommand.php # Jules task queue manager CLI
│       └── JulesQuotaCommand.php # Jules quota and concurrency monitor CLI
├── Http/
│   ├── Controllers/             # API & Inertia SPA controllers
│   └── Middleware/              # Route protection and Inertia request shared props
├── Models/                      # Eloquent models (User, PopmartAccount, Product, Sku, etc.)
└── Services/
    ├── Alerts/                  # Notification dispatchers (Telegram, Email)
    ├── Analytics/               # Box prediction and draw likelihood algorithms
    ├── Api/PopMart/             # Low-level HTTP endpoints, throttles, and headers
    ├── AutoLock/                # POP NOW lock triggers and claim workers
    ├── Jules/                   # Google Jules queue, quota tracker, and scheduler
    ├── Locator/                 # Country and region resolution (MY, SG, etc.)
    └── PopMart/                 # Sync sweepers, stock trackers, and adapters
        └── Sync/
            └── Adapters/        # Payload transformers (Spu, SkuStock, PopNowSet, LuckyPoints)
```

---

## 3. Database Schema & Migration Requirements

* **Engine:** MariaDB 10.11+.
* **Key Tables:**
  * `users`: Member records and authentication tokens.
  * `popmart_accounts`: Stores user sessions, auth cookies, and lucky point tallies.
  * `products` / `skus`: Pop Mart collectible figures and individual blind box SKUs.
  * `collections`: Pop Mart series (e.g. Labubu, Molly, Crybaby, Dimoo).
  * `box_sets`: POP NOW grid sessions with spatial tracking of remaining blind boxes.
  * `popmart_calls`: Audit log of outbound HTTP requests with status codes and throttles.
* **Important Database Note:**
  Do **not** attempt to run database migrations against an in-memory SQLite database (`:memory:`). The migrations use spatial coordinates (`POINT`, spatial indexes) and virtual generated columns that require a real MariaDB instance.

---

## 4. Testing & Verification

1. **Test Suites:**
   * `tests/Unit/`: Isolated domain logic, payload normalization, quota calculation, math.
   * `tests/Feature/`: Endpoint integration, middleware permissions, console commands, database state changes.
2. **Running Pest:**
   ```bash
   # Run all tests
   php artisan test --compact

   # Run a single feature test
   php artisan test --compact tests/Feature/PopMartSyncTest.php

   # Filter by test name
   php artisan test --compact --filter="calculates remaining rolling quota"
   ```
3. **Pint Code Formatting:**
   ```bash
   vendor/bin/pint --format agent
   ```

---

## 5. Google Jules Tooling

* **Quota Monitor:**
  Run `php artisan jules:quota` to check daily usage against Google Jules tiers (Free: 15/day, 3 concurrent; Pro: 100/day, 15 concurrent; Ultra: 300/day, 60 concurrent).
* **Queue Management:**
  Run `php artisan jules:queue list` to inspect active tasks, or `php artisan jules:queue dispatch` to process queued tasks when slots open.
* **Standalone Runner:**
  `scripts/jules-queue.php` can be invoked in CI or cron environments without full application boot overhead.
