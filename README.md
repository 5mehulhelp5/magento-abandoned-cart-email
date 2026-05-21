# ETechFlow Abandoned Cart Email for Magento 2

Recover lost sales with automated, personalised abandoned-cart email reminders. Fully compatible with Luma, Hyvä, and Adobe Commerce.

---

## Features

- **Configurable email sequences** — send up to 9 reminders per cart at custom intervals (e.g., 1 hour, 24 hours, 72 hours)
- **One-click cart restore** — customers click the link in the email and their cart is back exactly as they left it, optionally with auto-login
- **Auto-generated discount coupons** — incentivise recovery with unique single-use coupon codes per email
- **Open & click tracking** — measure exactly which emails are working and which carts they recovered
- **Rich targeting** — per-store, per-customer-group, by cart subtotal range, by item count, by Magento price-rule conditions
- **Guest cart support** — track and email visitors who reached checkout but never logged in
- **Test mode** — preview emails by redirecting to a dev inbox before going live
- **Unsubscribe link** — built into every email, with confirmation page
- **Recovery dashboard** — total abandoned, total recovered, recovery rate, revenue recovered, by date range
- **Per-rule analytics** — at-a-glance recovery rate per rule in the rules grid

---

## Compatibility

| | Open Source 2.4.6 | Open Source 2.4.7 | Adobe Commerce 2.4.6 | Adobe Commerce 2.4.7 |
|---|---|---|---|---|
| Luma theme | ✅ | ✅ | ✅ | ✅ |
| Hyvä theme 1.3+ | ✅ | ✅ | ✅ | ✅ |
| PHP | 8.1 / 8.2 | 8.2 / 8.3 | 8.1 / 8.2 | 8.2 / 8.3 |

---

## Installation

### Via Composer (recommended)

```bash
composer require etechflow/module-abandoned-cart
bin/magento module:enable Etechflow_AbandonedCart
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Manual (upload zip)

1. Upload the `ETechFlow/AbandonedCart` folder to `app/code/ETechFlow/AbandonedCart/` on your server
2. SSH in and run:

```bash
cd /path/to/magento
bin/magento module:enable Etechflow_AbandonedCart
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Verify the install

```bash
bin/magento module:status Etechflow_AbandonedCart
# Expected: Module is enabled
bin/magento etechflow:abc:verify
# Expected: ALL CHECKS PASSED
```

### Cron must be running

The module's emails are sent by Magento cron. If you haven't already set up Linux cron:

```bash
bin/magento cron:install
crontab -l   # verify it was added
```

---

## Configuration

Go to **Stores → Configuration → ETechFlow → Abandoned Cart Email**.

Key settings to review on first install:

- **General → Enable Module**: Yes
- **General → Cart Abandonment Threshold**: 30 minutes (default — adjust to your store's checkout flow)
- **License → License Key**: Paste the key from your purchase email (development hosts skip this)
- **Email Sending → Sender Identity**: Pick which "Store Email Address" sends the recovery emails
- **Cart Restore → Restore Token Expiry**: 30 days default

Then create your first rule under **Marketing → ETechFlow Abandoned Cart → Email Rules → Add New Rule**.

A typical sequence:
1. Rule 1: send 1 hour after abandonment, friendly reminder, no coupon
2. Rule 2: send 24 hours later, includes 5% coupon
3. Rule 3: send 72 hours later, "last chance" with 10% coupon

---

## CLI commands

| Command | Purpose |
|---|---|
| `bin/magento etechflow:abc:verify` | End-to-end smoke test. Run after install and after upgrade. |
| `bin/magento etechflow:abc:perf` | Micro-benchmark of hot paths. Add `--iterations=N` and `--json=path`. |
| `bin/magento etechflow:abc:send` | Force a cron tick now (don't wait for the schedule). |
| `bin/magento etechflow:abc:cleanup` | Force the cleanup cron now. |

---

## Performance

This module is built for high-traffic stores. Performance characteristics on warm cache:

| Hot path | p95 target |
|---|---|
| Frontend cart-save observer | < 0.5 ms |
| Per-cron-tick batch (50 carts) | < 1.5 s |
| 1-click restore controller | < 50 ms |
| Tracking pixel | < 20 ms |

Storefront pages take ZERO additional work from this module — all heavy lifting happens in cron.

---

## Hyvä theme

Hyvä compatibility ships in the same package. The module detects the active theme automatically and:

- Sends Hyvä-styled email templates (Tailwind classes) on Hyvä storefronts
- Sends Luma-styled templates on Luma storefronts
- Restore + Unsubscribe pages render with both Block (Luma) and ViewModel (Hyvä) paths — no Knockout on Hyvä

---

## Adobe Commerce

The module works fully on Adobe Commerce 2.4.6 / 2.4.7 and adds:

- Future v1.1.0: B2B company-account abandoned-cart support
- Future v1.1.0: Customer Segments integration
- Future v1.1.0: GraphQL endpoints for headless

v1.0.0 already handles multi-website / multi-store and AC's customer-group targeting.

---

## Uninstall

```bash
bin/magento module:disable Etechflow_AbandonedCart
composer remove etechflow/module-abandoned-cart
bin/magento setup:upgrade
```

Database tables (`etechflow_abandoned_cart`, `etechflow_abandoned_cart_rule`, `etechflow_abandoned_cart_email_log`) are dropped by the schema patch on uninstall.

---

## Support

- Email: etechflow0@gmail.com
- Website: https://etechflow.com
- Bug reports: include the output of `bin/magento etechflow:abc:verify`

---

## License

Proprietary. See `LICENSE.txt` for terms. A per-installation license is required for production use.