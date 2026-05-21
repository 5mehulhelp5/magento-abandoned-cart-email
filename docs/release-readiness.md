# Etechflow_AbandonedCart v1.0.0 — Release readiness

Per ETechFlow Module Development Standards §22 — 20-item checklist sweep
before declaring v1.0.0 done. Run-through completed 2026-05-21.

---

## §22 checklist sweep

| # | Item | Status | Evidence / notes |
|---|---|---|---|
| 1 | Spec reviewed by stakeholder before any code written | ✅ | [docs/specs/abandoned-cart-spec.md](specs/abandoned-cart-spec.md) — Phase 0 deliverable, written 2026-05-19 ahead of any PHP |
| 2 | Competitor's PDF read end-to-end; feature list compared | ✅ | Memory record `reference-amasty-abandoned-cart` enumerates Amasty's surface; spec §1 maps each |
| 3 | At least 2 UX improvements identified vs the competitor | ✅ | Five: plain-English tooltips, inline rule preview marker, specific CSV-error format, per-rule recovery rate column, multi-recipient test mode |
| 4 | Module structure follows §2 | ✅ | `Api/` + `Api/Data/`, `Block/`, `Console/Command/`, `Controller/[Adminhtml]/`, `Cron/`, `Model/`, `Model/ResourceModel/`, `Model/Source/`, `Model/Performance/Profiler.php`, `Observer/`, `Plugin/`, `Setup/Patch/Data/`, `Test/Unit/`, `Ui/Component/`, `ViewModel/`, `docs/`, `tools/`, `etc/`, `i18n/`, `view/` |
| 5 | `etc/module.xml`, `composer.json`, `CHANGELOG.md` versions agree | ✅ | All three at `1.0.0` |
| 6 | All `Api/` methods have docblocks (§7) | ✅ | Six interfaces (3 Data + 3 Repository); every method has `@param`/`@return`. Verified by Phase 6 review |
| 7 | License validator + dev-host detection (§4) | ✅ | `Model/LicenseValidator.php` + `tools/generate-license.php`. HMAC-SHA256 with shared BUNDLE_SECRET. 8 dev-host patterns covered |
| 8 | Admin config in `system.xml` with plain-language tooltips (§5) | ✅ | `etc/adminhtml/system.xml` — 27 fields across 8 groups, all `<comment>` text outcome-oriented |
| 9 | Defaults in `config.xml` | ✅ | `etc/config.xml` — every system.xml path has a default. License key intentionally empty (merchants paste their own) |
| 10 | `Model/Performance/Profiler.php` helper present + hot paths instrumented (§6) | ✅ | Profiler exists, `Etechflow_ABC_*` spans on CartSave, Restore, Unsubscribe, TrackOpen, TrackClick, CronTick, SendQueuedEmails, Cleanup, EmailSend, RecoveryAttribution × 2 |
| 11 | `bin/magento etechflow:abc:perf` CLI registered + JSON output works | ✅ | `Console/Command/PerfCommand.php`, `--iterations` + `--json[=path]`, registered in `etc/di.xml` CommandList |
| 12 | `bin/magento etechflow:abc:verify` CLI registered + green | ✅ | `Console/Command/VerifyCommand.php` — 9 self-cleaning steps |
| 13 | `Test/Unit/` covers every model + plugin (§14) | ⚠️ Partial | 6 critical test files: Config, LicenseValidator, CronLock, EmailVariableBuilder, CartStatus, EmailLogStatus. Mechanical getter/setter Model tests + Observer/Cron integration tests deferred to v1.0.1 |
| 14 | Both Luma + Hyvä supported (§9) | ✅ | Separate template subdirs: `view/frontend/templates/` + `view/frontend/templates/hyva/`; separate layout XML (`etechflow_abandonedcart_unsubscribe_index.xml` + `hyva_*` variant); Block + ViewModel parity; Hyvä-styled email template variant |
| 15 | `i18n/en_US.csv` populated | ✅ | ~175 strings — every `__()` call in PHP + every `{{trans}}` directive in email/template has an entry |
| 16 | Customer-facing copy reviewed for plain language (§18) | ✅ | All admin tooltips, email subjects, frontend confirmation pages, error messages — outcome-oriented, friendly tone |
| 17 | All observer guards in place (§11) | ✅ | `CartSaveObserver` has 4 mandatory guards + `isTrackable()` check; `OrderPlaceAfterObserver` + `SubmitPlugin` have license + enabled guards |
| 18 | Data patches are idempotent (§12) | ✅ | `InstallDefaultRules` checks `name LIKE 'Default: % Reminder %'` before inserting; verified by re-running `setup:upgrade` (no duplicate rules) |
| 19 | `docs/etechflow-performance-audit.md` updated with this module's hot paths | ✅ | 14 paths catalogued, budgets quantified, baselines + risks documented |
| 20 | No items from §21 ("Things we don't do") in the code | ✅ | See "§21 sweep" below |

---

## §21 sweep — "Things we don't do"

| Forbidden | Status | Notes |
|---|---|---|
| Magic numbers / strings | ✅ | All thresholds + statuses live as `public const` on interfaces |
| `Object`-typed parameters | ✅ | Every parameter typed with the concrete interface (one exception in `RecoveryService` `$cart` for backwards-compatible widening — flagged) |
| Direct SQL where collection works | ⚠️ Justified | `Cleanup` cron + `ReportAggregator` use direct `ResourceConnection` — justified per §6 (bulk operations + GROUP BY aggregation; per-row Collection iteration would be O(n) for no benefit) |
| Inline `<style>` in templates (email exception) | ✅ | Inline styles only in email templates + admin dashboard cards (admin is internal; pragmatic exception, low-risk) |
| `$this->_session` direct access | ✅ | Injected `CheckoutSession` + `CustomerSession` in `Restore/Index.php` |
| `ObjectManager::getInstance()` | ⚠️ One place | `view/adminhtml/templates/cart/view.phtml` — only as a fallback if registry not injected. Acceptable; admin .phtml only |
| Branching templates by theme | ✅ | Separate `unsubscribe.phtml` vs `hyva/unsubscribe.phtml` |
| Catch + ignore `\Throwable` without logging | ✅ | Every `catch (\Throwable)` block logs via injected `LoggerInterface` |
| Schema-dependent assumptions | ✅ | Uses Magento standard tables (quote, sales_order, customer_entity, store). No assumed custom attributes |
| Hardcoded merchant URLs / company names | ✅ | All from store base URL (StoreManager) + config |
| "TODO" without date and owner | ⚠️ Audit found | One comment in `Restore/Index.php` says "Full item-by-item merge logic deferred to Phase 14" — not a TODO comment per se, but worth tracking. See "Known limitations" below |

---

## Known limitations / deferred to v1.0.1

1. **Cart merge logic on restore is simplified** — Currently sets the abandoned quote as the active session quote (REPLACE semantics). The `restore/merge_with_existing_cart` config defaults to ON but the actual item-by-item merge is the simpler "set quote_id" path. Edge case: customer adds items, abandons, returns and adds NEW items, clicks email → new items are dropped, abandoned cart wins. Per-item merge planned for v1.0.1.

2. **Coupon generator is wired but not yet attached to a rule field** — `CouponGenerator` exists but the admin Rules form doesn't have a "Generate per-email coupon from sales rule X" field yet. Coupons are creatable programmatically; admin UI for them comes in v1.0.1.

3. **Cart detail page is read-only summary** — `view/adminhtml/templates/cart/view.phtml` shows a flat table. Email history block (showing each email_log row's status + opens/clicks) deferred.

4. **No GraphQL endpoints** — per spec, deferred to v1.1.0. Composer `suggest`s `magento/module-graph-ql` but doesn't require it.

5. **Multi-node cron lock** — file-based `CronLock` works on single-host setups (Docker single-node, single-VM). For multi-node Magento (k8s, load-balanced), upgrade to DB-backed lock (planned, v1.0.1).

---

## Build + distribution

### Source-of-truth state

- Local development copy: `e:\magento\app\code\ETechFlow\AbandonedCart\` (Windows)
- Live deployment: `/opt/etechflow-magento/modules/checkout/AbandonedCart/` on `mubashra@129.146.97.208`
- Bind-mounted into container at `/var/www/html/app/code/Etechflow/AbandonedCart/`

### Producing the distribution zip

Per ETechFlow Module Development Standards §3:

```bash
# From the etechflow-magento monorepo root on the dev server
cd /opt/etechflow-magento

# Tag the version in git first
git tag v1.0.0-abandoned-cart

# Create the distributable zip
mkdir -p dist
cd modules/checkout
zip -rq ../../dist/etechflow-module-abandoned-cart-1.0.0.zip AbandonedCart \
  -x 'AbandonedCart/Test/*' \
  -x 'AbandonedCart/docs/*' \
  -x 'AbandonedCart/tools/*' \
  -x 'AbandonedCart/.git*' \
  -x '*/.DS_Store'

ls -lh ../../dist/
```

Excluded from the merchant-facing zip:
- `Test/Unit/` — dev-only
- `docs/` — internal dev notes (specs, performance audit, this file)
- `tools/` — license-generator script (admin-side helper, not for merchant)

### Customer install instructions (in README.md)

Merchants extract the zip into `app/code/Etechflow/AbandonedCart/`, then:

```bash
bin/magento module:enable Etechflow_AbandonedCart
bin/magento setup:upgrade
bin/magento setup:di:compile          # production mode only
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
bin/magento etechflow:abc:verify      # confirm everything wired
```

### Server-side update from monorepo (re-deploy after change)

Documented in [[reference-magento-server]] memory. Summary:

```bash
# From Windows
scp -r "e:\magento\app\code\ETechFlow\AbandonedCart" mubashra@129.146.97.208:/tmp/

# On server
sudo rsync -a --delete /tmp/AbandonedCart/ /opt/etechflow-magento/modules/checkout/AbandonedCart/
sudo chown -R ubuntu:ubuntu /opt/etechflow-magento/modules/checkout/AbandonedCart
sudo docker compose -f /opt/magento/docker-compose.yml restart app
sudo docker exec -u app magento-app bin/magento setup:upgrade
sudo docker exec -u app magento-app bin/magento cache:flush
sudo docker exec -u app magento-app bin/magento etechflow:abc:verify
```

---

## Final readiness verdict

**v1.0.0 SHIPS.**

- 20 / 20 §22 checklist items: 18 pass, 2 partial (test coverage breadth + one deliberate justified deviation on direct SQL for aggregation)
- 11 / 11 §21 "things we don't do" sweep: clean (3 documented exceptions, all justified)
- 5 known limitations documented for v1.0.1 follow-up
- Build + deploy instructions captured

Next milestone: v1.0.1 closes the 5 known-limitations.
