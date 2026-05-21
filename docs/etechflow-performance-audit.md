# Etechflow_AbandonedCart — Performance audit

Per ETechFlow Module Development Standards §6. One-time inventory of every
hot path this module attaches to the Magento request lifecycle, with the
work each does per fire, the Tideways span name, and the perf budget.

Date written: 2026-05-21
Module version: 1.0.0

---

## Architecture summary

This module deliberately puts ALL expensive work behind cron. The
storefront-facing surface area is one observer (`CartSaveObserver`) plus
the four customer-facing controllers (Restore / Unsubscribe / Track-Open
/ Track-Click). Everything else — rule matching, email rendering, SMTP
transmission, cleanup — happens out-of-band.

> **Hot path budget:** the entire on-request cost added by this module to
> a normal Magento cart save must stay under **1 ms p95** when the module
> is enabled and the license is valid (warm cache).

Cron paths have generous budgets (seconds, not ms) because they're
bounded by `cron/max_runtime_seconds` (default 240) and `cron/batch_size`
(default 50).

---

## Hot path inventory

| # | Path type | Class | Event/Trigger | Profiler span | Budget (p95) |
|---|---|---|---|---|---|
| 1 | Observer | `Observer/CartSaveObserver` | `sales_quote_save_after` | `Etechflow_ABC_CartSaveTrack` | **< 0.5 ms** |
| 2 | Observer | `Observer/OrderPlaceAfterObserver` | `sales_order_place_after` | `Etechflow_ABC_RecoveryAttribution` | < 5 ms |
| 3 | Plugin (after) | `Plugin/Quote/SubmitPlugin` | `Magento\Quote\Model\QuoteManagement::submit` | `Etechflow_ABC_RecoveryAttributionPlugin` | < 5 ms |
| 4 | Cron | `Cron/SendReminders` | every 5 min | `Etechflow_ABC_CronTick` | < 240 s |
| 5 | Cron | `Cron/SendQueuedEmails` | every 5 min offset | `Etechflow_ABC_SendQueuedEmails` | < 240 s |
| 6 | Cron | `Cron/Cleanup` | daily 3 am | `Etechflow_ABC_Cleanup` | < 60 s |
| 7 | Email send | `Model/EmailSender::send` | called from cron | `Etechflow_ABC_EmailSend` | < 2 s per email (SMTP-bound) |
| 8 | Frontend controller | `Controller/Restore/Index` | `/etechflow_abandonedcart/restore/index/` | `Etechflow_ABC_Restore` | < 50 ms |
| 9 | Frontend controller | `Controller/Unsubscribe/Index` | `/etechflow_abandonedcart/unsubscribe/index/` | `Etechflow_ABC_Unsubscribe` | < 50 ms |
| 10 | Frontend controller | `Controller/Track/Open` | `/etechflow_abandonedcart/track/open/` | `Etechflow_ABC_TrackOpen` | < 20 ms |
| 11 | Frontend controller | `Controller/Track/Click` | `/etechflow_abandonedcart/track/click/` | `Etechflow_ABC_TrackClick` | < 30 ms |
| 12 | Admin grid | `view/.../cart_listing.xml` | admin GET | (Magento UI Component) | < 250 ms |
| 13 | Admin grid | `view/.../rule_listing.xml` | admin GET | (Magento UI Component) | < 100 ms |
| 14 | Admin report | `Block/Adminhtml/Report/Dashboard` | admin GET | (read-only aggregator) | < 500 ms |

No `ConfigProvider` — this module does NOT add anything to Magento's
checkout JS payload (zero frontend JS overhead per spec).

No `LayoutProcessor` plugins, no per-cart-item shipping rate plugins, no
ETechFlow-specific email-rendering plugins on order-confirmation. All
work is post-checkout, post-request.

---

## Per-path detail

### 1. CartSaveObserver (HOT PATH)

**Fires on:** every `sales_quote_save_after`. Magento triggers this on
add-to-cart, qty update, cart-rules recalculation, customer-info update.

**Work per fire:**
1. Five guards (per ETechFlow Module Development Standards §11):
   - `Config::isEnabled()` (1 cached config read)
   - `LicenseValidator::isValid()` (1 cached HMAC compare, ~0.01 ms after first call)
   - `_bulk_importer` flag read (1 array access)
   - `_indexer_processing` flag read (1 array access)
   - `isTrackable()` — checks `getId() > 0`, `getItemsCount() >= 1`,
     `getCustomerEmail() !== ''` (3 in-memory checks)
2. **If trackable**, one indexed SELECT on `etechflow_abandoned_cart` by
   unique `quote_id`. The unique index `ETECHFLOW_ABANDONED_CART_QUOTE_ID`
   makes this O(log n).
3. INSERT or UPDATE (depending on whether row exists). One round-trip.

**Worst case:** new cart, never tracked before → 1 SELECT + 1 INSERT (~5 ms
on a healthy MariaDB).

**Best case:** module disabled OR license invalid → 2 cached config reads,
< 0.05 ms.

**Risks:**
- Quote saves in tight loops (e.g., bulk import) — mitigated by the
  `_bulk_importer` guard.
- High-volume stores (10k+ active carts) — observer per-call cost stays
  flat; growth is in cron processing time, not observer time.

### 2. OrderPlaceAfterObserver

**Fires on:** `sales_order_place_after`, once per order placement.

**Work:** delegates to `RecoveryService::markRecovered()`. Finds the
abandoned-cart row by quote_id (indexed lookup), updates status to
RECOVERED, attributes the conversion to the most recently engaged
email_log row.

**Cost:** ~5 ms. Not a hot path — order placement is rare relative to
cart saves.

### 3. SubmitPlugin (defensive backup)

**Fires on:** `QuoteManagement::submit()` afterSubmit. Same work as
OrderPlaceAfterObserver via RecoveryService (which is idempotent).
**Cost:** identical to (2). The double-fire on the same RecoveryService
short-circuits to ~0.01 ms on the second pass (status check returns early).

### 4. SendReminders cron

**Fires:** every 5 minutes (`*/5 * * * *`).

**Work per tick:**
1. Acquire file lock (1 stat + 1 touch, < 1 ms).
2. Load every active rule (1 indexed SELECT on
   `ETECHFLOW_AC_RULE_ACTIVE_PRIORITY_AFTER`).
3. For each rule:
   - One SELECT on `etechflow_abandoned_cart` filtered by status=PENDING +
     abandoned_at cutoff + emails_sent threshold + rule conditions. Uses
     composite index `ETECHFLOW_ABANDONED_CART_STORE_STATUS_ABANDONED`.
   - For each matched cart: 1 INSERT into `email_log`, 1 UPDATE on cart.
4. Honor batch size (default 50 carts per tick) and max runtime
   (default 240 s).

**Healthy range:** processing 50 carts × 3 rules ≈ 1.5 s total. The cron
exits well before the 240 s limit on realistic workloads.

### 5. SendQueuedEmails cron

**Fires:** every 5 minutes offset (`2-57/5 * * * *`).

**Work per tick:**
- Load up to batch_size QUEUED `email_log` rows (1 SELECT).
- For each: call `EmailSender::send()` (template render + SMTP).
- Mark SENT or FAILED.

**Bottleneck:** SMTP round-trip (~100-500 ms per email depending on the
mail server). Batch of 50 = 5-25 seconds. Well under 240 s budget.

### 6. Cleanup cron

**Fires:** daily 3 am (`0 3 * * *`).

**Work:**
1. Bulk UPDATE: stale PENDING → EXPIRED (one query).
2. Bulk DELETE: email_logs past retention (one query).
3. Bulk DELETE: EXPIRED carts past retention (one query).

**Cost:** < 1 s even on stores with hundreds of thousands of historical
rows, because each is a single bounded SQL statement.

### 7. EmailSender (inside SendQueuedEmails)

**Per email:**
- Load cart (1 SELECT, indexed by entity_id).
- Build variables (in-memory string assembly, < 1 ms).
- TransportBuilder template render (Magento's email engine, ~50-200 ms).
- SMTP send (network-bound, 100-500 ms typically).
- Update email_log (1 UPDATE).

**Budget:** < 2 s per email, dominated by SMTP. Capped overall by cron's
batch_size + max_runtime.

### 8-11. Frontend controllers

Each is a single-purpose, read-mostly path:

| Controller | Reads | Writes | Notes |
|---|---|---|---|
| Restore | 1 SELECT (by token) + 1 SELECT (quote by id) | 0 | Checkout session set is in-memory |
| Unsubscribe | 1 SELECT (by token) | 1 UPDATE (status=UNSUBSCRIBED) | Idempotent — second call is no-op |
| Track Open | 1 SELECT (by log_id) | 1 UPDATE (counts + status) | Pixel response < 20 ms |
| Track Click | 1 SELECT (by log_id) + StoreManager allow-list | 1 UPDATE | Open-redirect safety check |

All four wrap their body in `try / catch (\Throwable)` and emit a
fail-safe response on error (homepage redirect or empty pixel).

### 12-14. Admin grids + reports

Standard Magento UI Component grids over our own collections. Pagination
limits row count; filters use indexed columns. Reports aggregator uses
single GROUP BY SQL per metric group — no PHP-level looping.

---

## Measured baselines (PerfCommand output expected ranges)

Run with `bin/magento etechflow:abc:perf --iterations=1000` after deploy
to capture pre-prod numbers. Sample ranges on a healthy server (Docker on
Linux, MariaDB 10.11, Redis cache, warm caches):

| Path | min | median | p95 | max | Threshold |
|---|---|---|---|---|---|
| `Etechflow_ABC_Config_isEnabled` | ~0.001 ms | ~0.005 ms | ~0.02 ms | ~0.5 ms | < 0.1 ms |
| `Etechflow_ABC_License_isValid` (cached) | ~0.0005 ms | ~0.001 ms | ~0.005 ms | ~0.1 ms | < 0.05 ms |
| `Etechflow_ABC_Config_getAbandonmentMinutes` | ~0.001 ms | ~0.005 ms | ~0.02 ms | ~0.5 ms | < 0.1 ms |
| `Etechflow_ABC_CartStatus_toOptionArray` | ~0.01 ms | ~0.02 ms | ~0.05 ms | ~0.3 ms | < 0.1 ms |
| `Etechflow_ABC_RestoreToken_generate` | ~0.01 ms | ~0.015 ms | ~0.05 ms | ~0.5 ms | < 0.1 ms |

If any path exceeds **2× threshold** on a clean run, investigate before
shipping. Common causes: stale config cache, debug logger enabled,
xdebug/observer interference.

---

## Risk areas

1. **License validation cold-start.** First call per request performs the
   HMAC compare + StoreManager lookup (~1 ms). All subsequent calls in
   the same request are cached on the validator instance. Risk: code
   paths that construct LicenseValidator multiple times per request
   would double-pay. Mitigation: singleton via DI (current setup),
   confirmed by `ConfigTest::testCachesResultWithinRequest()`.

2. **CartSaveObserver under bulk imports.** If a third party uses
   `\Magento\Quote\Model\Quote::save()` in a tight loop without setting
   `_bulk_importer`, our observer fires on every save. Mitigation:
   the four §11 guards bail early on the cheapest checks first; our worst
   case is still 2 cached reads per save.

3. **Token-generation entropy.** `random_bytes(32)` requires PHP's CSPRNG.
   On extremely resource-constrained hosts this can block briefly. Risk
   is theoretical — modern Linux's `getrandom(2)` is non-blocking after
   first seed. Not mitigated; would manifest as elevated
   `Etechflow_ABC_RestoreToken_generate` p95 in PerfCommand output.

4. **SendQueuedEmails SMTP backpressure.** If the merchant's SMTP server
   is slow, batch_size emails × slow_per_email can exceed
   max_runtime_seconds. Mitigation: the cron stops gracefully at
   max_runtime and resumes on the next tick. No data loss; just delayed
   delivery.

---

## Future work

- Replace file-based `CronLock` with database-backed lock (`Magento\Framework\Lock\Backend\Database`) for multi-node Magento deployments. File locks don't synchronize across hosts.
- Magento Message Queue (RabbitMQ) consumer for SendQueuedEmails to decouple from cron cadence — would enable burst processing of large queues.
- Coloured status badges in admin Carts grid (`Ui/Component/Listing/Column/CartStatusBadge.php`).
- Profiler instrumentation around DataProvider class methods (admin grid load).

---

## How to rerun this audit

After every minor/major version bump:

1. Run `bin/magento etechflow:abc:perf --iterations=2000 --json=audit/perf-vX.Y.Z.json`.
2. Diff against the previous file. Any path > 2× change = regression.
3. Update the "Measured baselines" table above.
4. Bump the date at the top of this file.
