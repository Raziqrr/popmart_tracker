# Google Jules Agent Rules & Execution Guidelines

This file provides system instructions and execution constraints for **Google Jules** when operating autonomously in this repository.

---

## 1. Environment & Prerequisites

* **PHP Version:** PHP 8.5 (compatible with PHP 8.3/8.4/8.5).
* **Required PHP Extensions:** `mbstring`, `intl`, `pdo_mysql`, `pdo_sqlite`, `curl`, `zip`, `fileinfo`, `bcmath`, `pcntl`.
* **Database (Critical):**
  * This application **requires MariaDB 10.11+** for database tests and migrations.
  * **Do not use SQLite in-memory for database migrations:** The migrations utilize spatial columns (`POINT`, `SPATIAL INDEX`) and generated virtual/stored columns (`VIRTUAL GENERATED`) that SQLite cannot execute.
  * In the test container / CI environment, connect to MariaDB:
    ```env
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=popmart_tracker_test
    DB_USERNAME=root
    DB_PASSWORD=password
    ```
* **Frontend:** Node.js 22, Inertia.js v3, React 19, Tailwind CSS. Run `npm ci && npm run typecheck && npm run build` when touching `resources/js`.

---

## 2. Coding Conventions & Standards

Follow Laravel conventions and existing codebase architecture:

1. **Strict Types & Declarations:**
   * Always provide explicit parameter type hints and return type declarations on every method:
     ```php
     public function track(string $taskId, ?Carbon $dispatchedAt = null): bool
     ```
   * Use PHP 8 constructor property promotion:
     ```php
     public function __construct(
         public readonly JulesQuotaTracker $quotaTracker,
         public readonly string $storagePath,
     ) {}
     ```
2. **Control Structures:**
   * Always use curly braces for control structures, even for single-line bodies.
3. **Array Shapes & PHPDocs:**
   * Annotate complex arrays with PHPDoc array shape types:
     ```php
     /**
      * @return array{tier: string, daily_used: int, daily_limit: int, active_tasks: int, remaining_capacity: int}
      */
     public function getMetrics(): array
     ```
4. **Enums:**
   * Use TitleCase for enum cases (e.g. `case Urgent; case High; case Normal;`).
5. **Formatting (Mandatory):**
   * Before finalizing any changes, run Laravel Pint to adhere to project formatting:
     ```bash
     vendor/bin/pint --format agent
     ```
     or on dirty files:
     ```bash
     vendor/bin/pint --dirty --format agent
     ```

---

## 3. Test Enforcement & Pest

* **Test Framework:** This repository uses **Pest** (built on PHPUnit).
* **Running Tests:**
  * Run the narrowest set of tests that covers your change:
    ```bash
    php artisan test --compact --filter=SpecificTestName
    ```
    or directly via Pest:
    ```bash
    vendor/bin/pest tests/Feature/SpecificTest.php
    ```
* **Non-Negotiable Rules:**
  * **Never weaken, skip, or delete existing tests** to make a pull request pass. An existing test failure indicates a regression or bug in the change.
  * Add regression tests for every bug fix and feature tests for every new feature.
  * Use Model Factories and `fake()` for test fixture generation.
  * Mock external HTTP endpoints using `Http::fake()`.

---

## 4. Pop Mart Domain Guidelines

* **API Endpoints:** Located under `app/Services/Api/PopMart/Endpoints/{Domain}/{EndpointName}.php`.
* **Sync Adapters:** Located under `app/Services/PopMart/Sync/Adapters/`.
  * Adapters map raw vendor JSON payloads to strongly typed internal schemas.
  * Adapters expose `public const CONFIRMED = true|false;`. Set to `true` only when field keys are verified against live responses.
* **Probing Endpoints & Adapter Confirmation:**
  * Adapters begin as `CONFIRMED = false` with guessed field structures until verified against actual responses.
  * Remaining unconfirmed adapter: `LuckyPointsAdapter` (`app/Services/PopMart/Sync/Adapters/LuckyPointsAdapter.php`) which maps the member points task payload (`GetTasksByActivityAndChannel`).
  * Use the capture tool to inspect, verify, and confirm payloads:
    ```bash
    # Probe and verify adapter against live endpoint response:
    php artisan popmart:capture GetTasksByActivityAndChannel '{"activityId":"..."}' --check --account=1

    # Probe SPU catalog endpoint:
    php artisan popmart:capture FindSummariesByCollectionId '{"collectionId":"..."}' --check
    ```
  * Once verified, flip `public const CONFIRMED = true;` on the adapter.
* **Exception Handling:** Always throw or catch `PopMartException` backed by the `PopMartError` enum.

---

## 5. Jules Task Protocol & PR Checklist

When Jules is assigned an issue or task:

1. **Review Instructions:** Read the issue description, affected files list, and acceptance criteria carefully.
2. **Inspect Sibling Code:** Inspect adjacent files in the target directory to mirror patterns, naming, and dependency injection style.
3. **Implement Changes:** Keep diffs minimal, clean, and directly aligned with the prompt. Do not perform unrelated refactoring.
4. **Verify Locally:**
   * Run targeted Pest tests: `php artisan test --compact --filter=YourTest`
   * Run Pint formatting: `vendor/bin/pint --format agent`
5. **Open Pull Request:**
   * Title formatted with Conventional Commits (e.g., `feat(jules): ...` or `fix(adapter): ...`).
   * PR description must link to the triggering issue (`Closes #123`), summarize what changed, list tests executed, and document edge cases handled.
