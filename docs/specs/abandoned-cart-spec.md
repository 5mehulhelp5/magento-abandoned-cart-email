# Etechflow_AbandonedCart — Feature Specification

**Version:** 1.0.0
**Status:** Draft for stakeholder review
**Author:** ETechFlow Engineering
**Date:** 2026-05-19
**Competitor reference:** Amasty Abandoned Cart Email for Magento 2 (https://amasty.com/abandoned-cart-email-for-magento-2.html)

> Per [ETechFlow Module Development Standards §1.4](../../../docs/module-development-standards.md), this spec exists to be revised cheaply in Markdown before it becomes expensive in PHP. Stakeholders should redline this before code begins.

---

## 1. What the feature does

Etechflow_AbandonedCart automatically detects shopping carts that customers leave behind and sends a configurable sequence of recovery emails to win those carts back. Carts are detected via cron (not via per-request scanning, so the storefront stays fast), and each recovery email contains a personalised, one-click "restore your cart" link plus optional auto-generated discount coupon. When the customer clicks the link, their cart is restored exactly as they left it, and if they complete the purchase the module attributes the sale back to the originating email so merchants can measure recovery rate and revenue.

The module ships with sensible defaults and silent failure modes — a misconfigured module never breaks the storefront. Existing merchants who install it see zero behavioural change until they explicitly enable rules in the admin.

---

## 2. Admin configuration

Stores → Configuration → ETechFlow → Abandoned Cart Email. All paths under `etechflow_abandoned_cart/`.

### 2.1 General

| Field | Path | Default | Tooltip (plain-English, per §18) |
|---|---|---|---|
| Enable Module | `general/enabled` | Yes | Turn the whole extension on or off. When off, no emails are sent and no carts are tracked. |
| Cart Abandonment Threshold | `general/abandonment_threshold_minutes` | 30 (min) | How long a cart must sit idle before we treat it as abandoned. 30 minutes is a good starting point — too short and you'll email shoppers who are still browsing, too long and you'll miss the recovery window. |
| Debug Mode | `general/debug` | No | When on, the module writes verbose details to `var/log/etechflow_abandoned_cart.log`. Leave off in production. |
| Test Mode | `general/test_mode` | No | When on, ALL recovery emails are redirected to the Test Recipient Email below — the real customer never receives anything. Use this when previewing a rule before going live. |
| Test Recipient Email | `general/test_recipient_email` | (empty) | Where to send emails while Test Mode is on. Supports a single email address. |

### 2.2 Email Sending

| Field | Path | Default | Tooltip |
|---|---|---|---|
| Sender Name | `email/sender_name` | General Contact | Shown as the "From" name when the customer receives the email. |
| Sender Identity | `email/sender_identity` | general | Which Magento sender identity ("General Contact", "Sales", "Customer Support") to use. Set up identities in Stores → Configuration → General → Store Email Addresses. |
| Reply-To Email | `email/reply_to` | (empty) | Optional. If set, replies from customers go here instead of the sender identity's address. |
| BCC Email | `email/bcc` | (empty) | Optional. Receive a copy of every recovery email — useful for monitoring during launch. Leave empty in normal operation. |
| Maximum Emails per Cart | `email/max_emails_per_cart` | 3 | How many reminders a single abandoned cart can ever receive. Stops the module from harassing the same customer with the same cart. |
| Default Email Template | `email/default_template` | etechflow_abandoned_cart_default_template | The template a rule uses when its own template field is left blank. |

### 2.3 Cart Restore

| Field | Path | Default | Tooltip |
|---|---|---|---|
| Restore Token Expiry | `restore/token_expiry_days` | 30 (days) | Each recovery link contains a one-time token. After this many days, the link stops working. Longer = more conversions, shorter = more secure. |
| Auto-Login Customer on Restore | `restore/auto_login_customer` | Yes | When a logged-in customer clicks their recovery link, log them straight back in. Guests are not affected. |
| Merge with Existing Cart | `restore/merge_with_existing_cart` | Yes | If the customer has already built a new cart in the meantime, this merges the recovered items into it. When off, the recovered cart replaces the new one. |

### 2.4 Tracking

| Field | Path | Default | Tooltip |
|---|---|---|---|
| Enable Open Tracking | `tracking/enable_open_tracking` | Yes | Embeds a tiny invisible image in every email so we can tell when it gets opened. |
| Enable Click Tracking | `tracking/enable_click_tracking` | Yes | Wraps every link in the email so we can record clicks. Required to attribute recovered orders back to specific emails. |
| UTM Source | `tracking/utm_source` | etechflow_abandoned_cart | Added to every link as `?utm_source=...` for Google Analytics. |
| UTM Medium | `tracking/utm_medium` | email | |
| UTM Campaign | `tracking/utm_campaign` | cart_recovery | |

### 2.5 Cron / Processing

| Field | Path | Default | Tooltip |
|---|---|---|---|
| Batch Size | `cron/batch_size` | 50 | How many carts to process per cron tick. Larger batches finish faster but spike server load. Magento's default cron runs every 5 minutes, so 50 ≈ 600 carts/hour. |
| Lock Timeout | `cron/lock_timeout_minutes` | 15 | Safety net — if the cron crashes mid-run, the lock auto-releases after this many minutes so the next tick can take over. |
| Maximum Runtime | `cron/max_runtime_seconds` | 240 | The cron stops itself after this many seconds to leave room for Magento's other cron jobs. |

### 2.6 Cleanup

| Field | Path | Default | Tooltip |
|---|---|---|---|
| Email Log Retention | `cleanup/log_retention_days` | 180 (days) | Old email send logs are deleted after this. Keep enough to fill your reporting window — 6 months by default. |
| Expired Cart Retention | `cleanup/expired_cart_retention_days` | 90 (days) | Carts that are abandoned but never recovered get deleted after this. The original `quote` row stays untouched — only our tracking row goes. |

### 2.7 Hyvä Compatibility

| Field | Path | Default | Tooltip |
|---|---|---|---|
| Use Hyvä-Compatible Email Templates | `hyva/enabled` | Yes | When you're running the Hyvä theme, send emails with the Hyvä-styled templates (Tailwind classes, no Knockout). When you're on Luma, this is ignored. |

### 2.8 License

| Field | Path | Default | Tooltip |
|---|---|---|---|
| License Key | `license/key` | (empty) | Paste the license key you received after purchase. The module silently disables itself if this is wrong or missing. Development hosts (`.test`, `.local`, `localhost`, etc.) bypass this automatically. |
| Production Environment | `license/is_production` | Yes | When set to No, license checks are skipped for development. |

---

## 3. Storefront behaviour

### 3.1 Cart tracking (silent)

There is NO visible change to the storefront when the module is enabled. Customers do not see anything about "abandoned carts." Tracking happens silently:

- Every time a cart is saved (item added, qty changed, etc.) an observer records the cart's identity for later evaluation by cron.
- Per [§6 Performance], observers carry the four mandatory guards (enabled check, bulk-importer flag, indexer-processing flag, relevant-change check) and never hit external services.

### 3.2 Recovery email link → cart restore page

When a customer clicks the recovery link in their email:

- **URL pattern:** `/etechflow/cart/restore/?token=<base64-restore-token>`
- The controller validates the token (expiry, single-use flag).
- **Success:** Cart is restored. If logged-in and Auto-Login is on, the session is restored. The customer lands on `/checkout/cart/` with a flash message "Your cart has been restored."
- **Token expired:** Redirect to `/customer/account/login/` (logged-out) or `/` (logged-in) with flash message "This restore link has expired."
- **Invalid token:** Same as expired — never reveal whether the token was valid-but-expired vs never-valid.

### 3.3 Unsubscribe link

Every recovery email has a footer "Stop receiving these emails":

- **URL:** `/etechflow/cart/unsubscribe/?token=<base64-token>`
- Confirmation page with a clear message: "You have been unsubscribed from cart recovery emails."
- Updates `etechflow_abandoned_cart.status = 5 (UNSUBSCRIBED)` for that cart's email address; future carts for the same email also skipped.

### 3.4 Open / click tracking

- Open: 1×1 transparent GIF served by `Controller/Track/Open.php`. Logs `etechflow_abandoned_cart_email_log.opened_at` + `open_count`.
- Click: All links wrapped through `Controller/Track/Click.php` → 302 redirect to the real URL after logging.
- Both tracking controllers are no-op when the corresponding admin setting is off.

---

## 4. Edge cases

These came from reading Amasty's manual and merchant feedback patterns. Each MUST be handled or explicitly out-of-scope.

| Case | Behaviour |
|---|---|
| Customer has no email (guest who never reached checkout) | Skipped — no email to send to. We don't track those carts. |
| Customer is logged out, abandons cart, logs in later from a different device | `CustomerLoginObserver` stitches the guest's tracked cart to the logged-in customer if the email matches. |
| Customer has already received max_emails_per_cart reminders | Cron skips that cart — never re-evaluates. |
| Cart still has items but customer has already converted | `OrderPlaceAfterObserver` marks cart as RECOVERED with `recovered_order_id`. Cron stops sending. |
| Items in cart are now out-of-stock | Email still sent. Customer is told only when they click and reach checkout — same as Amasty. |
| Items in cart have been deleted from catalog | Email still sent but the item row is greyed-out with "No longer available." |
| Cart subtotal has changed (price update on the product) | Email shows the CURRENT subtotal, not the original. |
| Customer is in a customer group excluded by the rule | Skipped at rule-matching stage. |
| Customer's email bounced earlier | Tracked via `email_log.status = FAILED`. Three consecutive failures → cart marked EXPIRED. |
| Multi-store: same customer has carts on different stores | Treated as separate carts. Rules are scoped per-store. |
| Currency: cart was in EUR, customer's email locale is USD | Email shows the cart's currency, not the customer's. |
| Cart contains a configurable product variant | Email shows the selected option (e.g., "Red, XL"). |
| Cart contains a bundle / grouped product | Shown as the parent product name with a "(bundle includes ...)" note. |
| Customer clicks restore link after token expiry | Friendly "This link has expired" message + offer to log in. |
| Customer clicks restore link, then closes browser, never converts | Cart stays in PROCESSING. Next cron evaluates the next rule in the sequence. |
| Two rules match the same cart | The rule with the lowest `priority` value wins. |
| Module is disabled mid-day | No further emails. In-flight cron tick drains current batch then exits cleanly. |
| License is invalid | `Model/Config::isEnabled()` returns false silently. ONE admin notice. Storefront unaffected. |
| Hyvä installed but no Hyvä-styled email template available | Falls back to default Luma-styled template. Logged as warning. |
| Database is on a read replica during cron | Cron uses the write connection explicitly. |
| Site is under heavy load, cron tick overruns max_runtime_seconds | Cron exits gracefully mid-batch. Next tick picks up remaining carts. |

---

## 5. Out of scope (v1.0.0)

Explicitly deferred to future versions to keep v1.0.0 shippable:

- **SMS / WhatsApp / push notifications** — email only.
- **A/B testing of email variants** — one template per rule.
- **Predictive sending time** — fixed offset only.
- **Wishlist abandonment** — only cart abandonment.
- **Product-view abandonment** — cart only.
- **GraphQL endpoints** — composer `suggest`s `magento/module-graph-ql` but no resolvers in v1.0.0. Planned v1.1.0.
- **B2B company-cart support** — composer `suggest`s `magento/module-company`. Planned v1.1.0.
- **Customer Segments integration (Adobe Commerce)** — basic customer group filtering only.
- **Page Builder for email templates** — plain HTML templates in v1.0.0.
- **Multi-language email body per rule** — relies on Magento's standard locale fallback.

---

## 6. UX improvements vs Amasty (per §1.3, ≥2 mandatory)

Five concrete improvements baked into v1.0.0:

1. **Plain-English tooltips on every config field** — Amasty's tooltips use technical jargon. Ours pass the "read it aloud to a non-technical merchant" test.
2. **Inline rule preview** — On the rule edit page, show "This rule would have matched 47 carts in the last 30 days" so the merchant knows the rule is alive before saving.
3. **CSV-error specificity** — Import errors say "row 14, column `min_subtotal`: expected number, got 'abc'", not "row 14 invalid".
4. **Per-rule recovery rate in the rules grid** — At-a-glance "this rule recovers 12.3% of carts" without opening Reports.
5. **Test Mode supports multi-recipient** — Comma-separated test inbox addresses (Amasty supports one).

---

## 7. Compatibility matrix

| | OS 2.4.6 | OS 2.4.7 | AC 2.4.6 | AC 2.4.7 |
|---|---|---|---|---|
| Luma | ✅ | ✅ | ✅ | ✅ |
| Hyvä 1.3+ | ✅ | ✅ | ✅ | ✅ |
| PHP 8.1 | ✅ | — | ✅ | — |
| PHP 8.2 | ✅ | ✅ | ✅ | ✅ |
| PHP 8.3 | — | ✅ | — | ✅ |

---

## 8. Required infrastructure (per ETechFlow standards)

Baseline for every ETechFlow module — not "features" but MANDATORY in v1.0.0:

- HMAC license validator with dev-host detection + bundle support (§4)
- `Model/Performance/Profiler.php` with Tideways-aware spans on hot paths (§6)
- `Model/Config.php` wrapping ScopeConfigInterface with typed getters (§5)
- Full `Api/` service contracts with docblocks (§7)
- `bin/magento etechflow:abc:verify` end-to-end smoke (§8)
- `bin/magento etechflow:abc:perf` micro-benchmark (§6)
- Both Luma and Hyvä first-class (§9) — separate `templates/hyva/`
- `etc/frontend/di.xml` for frontend-scoped composites (§10)
- Mandatory observer guards on every observer (§11)
- Idempotent data patches (§12)
- `Test/Unit/` 100% coverage on Model + Plugin (§14)
- Plain-English customer-facing copy (§18)
- `CHANGELOG.md` / `module.xml` setup_version / `composer.json` version always agreeing (§3)