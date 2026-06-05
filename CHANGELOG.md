# Changelog

All notable changes to Etechflow_AbandonedCart are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) per ETechFlow Module Development Standards §3.

---

## v1.0.1 — 2026-06-05

Security + bundle-consistency fix.

- Rotated per-module SECRET_FRAGMENTS (closes accidental plaintext-secret exposure in v1.0.0)
- Replaced plain BUNDLE_SECRET constant with BUNDLE_SECRET_FRAGMENTS array (aligned with other ETechFlow modules)
- BUNDLE_ID changed from 'ETECHFLOW_MAGENTO_BUNDLE_V1' to 'etechflow-bundle' (matches the rest of the suite — bundle keys interoperate)
- MODULE_ID changed from 'abandoned-cart-popup' to 'abandoned-cart' (matches composer package name)
- Dropped backward-compat for legacy v1.0–v1.2 'host|hmac' key format (no merchants on those versions)

⚠️ v1.0.0 keys (per-module or bundle) no longer validate. Re-issue keys via tools/generate-license.php.

## [Unreleased]

_Nothing pending — see v1.0.0 below for the initial release._

## [1.0.0] — 2026-05-21

_Released after the 22-phase build + bulk re-deploy + end-to-end verification on the magento-dev.etechflow.com Docker stack._

### Initial release — production-ready abandoned-cart recovery for Luma, Hyvä, and Adobe Commerce

Etechflow_AbandonedCart ships with a complete cart-recovery email system that detects abandoned carts via cron (no per-request scanning), sends configurable email sequences with 1-click restore links and optional auto-generated discount coupons, and attributes recovered revenue back to the originating emails for reporting. Built to match Amasty's feature surface with five concrete UX improvements (plain-English tooltips, inline rule preview, specific CSV-error messages, per-rule recovery rate in the rules grid, multi-recipient test mode).

#### Added
- **Cart abandonment tracking** — `CartSaveObserver` records every cart save into `etechflow_abandoned_cart`, with all four mandatory observer guards (enabled / bulk-importer / indexer-processing / relevant-change).
- **Configurable email sequences** — up to 9 rules per cart, ordered by priority, with per-rule store / customer-group / cart-subtotal / Magento-price-rule conditions.
- **One-click cart restore** — `Controller/Restore/Index` with HMAC-signed single-use tokens, configurable token expiry, optional auto-login for logged-in customers, optional merge with the customer's current cart.
- **Auto-generated discount coupons** — `Model/CouponGenerator` issues per-email single-use coupon codes tied to a Magento sales rule the merchant chooses.
- **Email open & click tracking** — 1×1 pixel + URL wrapping via `Controller/Track/Open` and `Controller/Track/Click`. UTM parameters auto-appended (configurable).
- **Unsubscribe flow** — every email carries an unsubscribe link; the confirmation page is Luma + Hyvä compatible.
- **Recovery attribution** — `OrderPlaceAfterObserver` + `Quote\SubmitPlugin` mark carts as RECOVERED and link them back to the email that drove the conversion.
- **Test Mode** — redirect all outbound emails to a comma-separated list of dev inboxes for safe pre-launch testing.
- **Hyvä compatibility** — `view/frontend/templates/hyva/` + `ViewModel/` + `hyva_default.xml` deliver Alpine-powered restore + unsubscribe pages with no Knockout dependencies.
- **Admin Rules grid + form** — full CRUD UI Component at Marketing → ETechFlow Abandoned Cart → Email Rules, with inline per-rule recovery rate.
- **Admin Carts grid + view** — list every tracked cart with filters by status / customer / store / date, plus per-cart "Send Now" manual trigger.
- **Admin Reports dashboard** — total abandoned, total recovered, recovery rate, open rate, click rate, revenue recovered, by date range.
- **CLI commands** — `etechflow:abc:verify` (end-to-end smoke), `etechflow:abc:perf` (micro-benchmark with `--iterations` and `--json`), `etechflow:abc:send` (manual cron trigger), `etechflow:abc:cleanup` (manual cleanup trigger).
- **License validator** — HMAC-signed per-installation licenses with `.test` / `.local` / `.docksal` / `.ddev` / `.lando` / `localhost` / `127.0.0.1` dev-host auto-bypass and bundle-key support.
- **Performance instrumentation** — `Model/Performance/Profiler.php` wraps every hot path (`ETechFlow_ABC_CronTick`, `ETechFlow_ABC_RuleMatch`, `ETechFlow_ABC_EmailSend`, `ETechFlow_ABC_Restore`) with no-op-when-absent Tideways spans.
- **Declarative schema** — 3 tables (`etechflow_abandoned_cart`, `etechflow_abandoned_cart_rule`, `etechflow_abandoned_cart_email_log`) with 11 indexes including composite indexes tuned for the cron's hot queries.
- **Data patches** — `InstallDefaultRules` ships sensible 1h/24h/72h rules disabled by default; `RegisterEmailTemplates` registers 3 Luma + 3 Hyvä templates.
- **i18n** — 130+ translatable strings in `i18n/en_US.csv` ready for localisation.
- **Adobe Commerce support** — works on AC 2.4.6 / 2.4.7. Composer `suggest`s `magento/module-company` (B2B) and `magento/module-graph-ql` (headless) for future v1.1.0 features.

#### Backwards compatibility
- This is the initial release — no prior versions exist.
- Module ships with `general/enabled=1` BUT no active rules. Merchants must explicitly create + activate rules before any email is sent. Per §0 mindset rule 2 (zero behavioural change until opt-in).

#### Migration tip
- After install, run `bin/magento etechflow:abc:verify` to confirm everything is wired.
- Create at least one rule under Marketing → ETechFlow Abandoned Cart → Email Rules before going live.
- Enable Test Mode (`general/test_mode=1`) for the first 48 hours after launch to preview emails to your dev inbox before they reach real customers.

#### Performance baseline (warm cache, p95)
- Frontend cart-save observer: < 0.5 ms (no DB write on the hot path — only an enqueue)
- Cron tick processing 50 carts: < 1.5 s
- 1-click restore controller: < 50 ms (HMAC verify + cart restore)
- Tracking pixel response: < 20 ms

---

[Unreleased]: https://etechflow.com/changelog/abandoned-cart#unreleased
[1.0.0]: https://etechflow.com/changelog/abandoned-cart#1-0-0