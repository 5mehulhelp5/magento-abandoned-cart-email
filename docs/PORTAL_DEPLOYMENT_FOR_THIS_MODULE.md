# Portal Setup for Etechflow_AbandonedCart Licensing

**Audience:** ETechFlow internal team only.
**Prerequisite:** `store-portal-admin` (Flask app) deployed and reachable from Magento installs.

This document walks you through the **portal-side** changes needed so the Magento module's SP-XXXX subscription flow works end-to-end. The Magento module (v1.3.0+) already has all the client-side wiring — what's left is:

1. Adding the 3 abandoned-cart plans (weekly / monthly / yearly) to the portal's `license_engine.py`
2. Verifying the portal endpoints are reachable
3. Setting up Stripe webhooks for renewal/suspension
4. Configuring the Magento module's License Portal URL to point at the portal

---

## 1. Add Plans to `license_engine.py`

The portal's `license_engine.py` PLANS dict needs 3 new entries. Open the file (in your store-portal-admin folder), find the `PLANS` dict, and add:

```python
PLANS = {
    # ... existing plans (bisn_*, dd_*, solo, growth, etc.) ...

    # ── Etechflow_AbandonedCart module plans (v1.3.0) ─────────────────────
    'abc_weekly': {
        'name': 'Abandoned Cart — Weekly',
        'price_weekly': 9,
        'billing_interval': 'week',
        'module': 'abandoned-cart-popup',
        'features': 'Cart recovery emails, exit-intent popups, visual templates, mobile triggers, reports',
        'support': 'Email — 48h',
    },
    'abc_monthly': {
        'name': 'Abandoned Cart — Monthly',
        'price_monthly': 29,
        'billing_interval': 'month',
        'module': 'abandoned-cart-popup',
        'features': 'Cart recovery emails, exit-intent popups, visual templates, mobile triggers, reports',
        'support': 'Email — 48h',
    },
    'abc_yearly': {
        'name': 'Abandoned Cart — Yearly',
        'price_yearly': 290,
        'billing_interval': 'year',
        'module': 'abandoned-cart-popup',
        'features': 'Cart recovery emails, exit-intent popups, visual templates, mobile triggers, reports',
        'support': 'Priority email — 24h',
    },
}
```

**Add the MODULE_ID:**

```python
MODULE_IDS = {
    # ... existing entries ...
    'abandoned_cart': 'abandoned-cart-popup',
}
```

**Restart Flask** (the spec specifies `use_reloader=False`, so manual restart is mandatory):

```bash
# In the store-portal-admin folder
./run.sh    # Linux/Mac
# OR
run.bat     # Windows
```

---

## 2. Verify Portal Endpoints

The portal already exposes these endpoints — confirm they respond:

| Endpoint | Method | Purpose |
|---|---|---|
| `/license/validate?domain=&license_key=&platform=magento&module=abandoned-cart-popup` | GET | Strict per-key lookup, returns `{valid:bool, ip_blocked:bool}` |
| `/license/activate` | POST | Verifies Stripe session, creates subscription, mints SP-XXXX key |
| `/payment/stripe/create-session` | POST | Optional — Magento creates Stripe sessions directly via cURL |

Test:

```bash
# Should return {valid: false} (no key exists yet)
curl 'http://localhost:5001/license/validate?domain=shop.example.com&license_key=SP-TEST&platform=magento&module=abandoned-cart-popup'
```

---

## 3. Stripe Webhooks (Optional but Recommended)

Stripe sends events when subscriptions are paid, fail, or cancel. Configure these on Stripe Dashboard → Developers → Webhooks → Add Endpoint:

| Endpoint URL | Events |
|---|---|
| `https://your-portal-url/payment/stripe/webhook` | `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.deleted` |

On `invoice.payment_failed` → portal sets subscription `is_active=False` → next `/license/validate` call returns `valid:false` → Magento module locks down within 30 seconds (cache TTL).

On reactivation → portal sets `is_active=True` → next validation returns `valid:true` → Magento auto-restores from `issued_key`.

---

## 4. Configure Magento Module

On the customer's Magento install (or yours for testing), set the portal URL:

```
Admin → Stores → Configuration
  → ETechFlow → Abandoned Cart Email
  → License → License Portal URL

Value: https://your-portal-url/license/validate
```

If you leave blank, the default `https://license.etechflow.com/license/validate` is used.

Also set Stripe keys:

```
Admin → Stores → Configuration → ETechFlow → Abandoned Cart Email
  → Payment Settings (Stripe)
    → Stripe Secret Key:      sk_test_xxx   (or sk_live_xxx for production)
    → Stripe Publishable Key: pk_test_xxx
    → Currency Code:          usd           (or your currency)
```

---

## 5. End-to-End Smoke Test

1. **Customer-side (Magento admin):**
   - Visit Marketing → ETechFlow → Abandoned Carts → redirects to License Gate ✓
   - Click "Subscribe Monthly" → enter name+email → Continue to Stripe ✓
   - Use Stripe test card `4242 4242 4242 4242` → any future date + any CVC
   - Stripe redirects back → Activation page shows SP-XXXX key ✓
   - Cache flush → admin grids accessible ✓

2. **Portal-side verification:**
   - Open portal admin: `http://localhost:5001/admin`
   - Subscriptions section → new row for the test domain ✓
   - License key starts with `SP-` ✓

3. **IP-block test:**
   - In portal admin, edit subscription → add a different allowed IP
   - Magento module: refresh admin → key cleared, IP-block flag set
   - Portal admin: restore actual IP
   - Magento module: refresh again → key auto-restored from issued_key ✓

4. **Suspension test:**
   - Portal admin: set subscription `is_active=False`
   - Within 30s, Magento module: admin grids redirect to gate ✓
   - Portal admin: set back to active
   - Within 60s, Magento module: grids load again ✓

---

## 6. Module Identity Reference

For copying into the portal database / dashboards:

| Property | Value |
|---|---|
| MODULE_ID | `abandoned-cart-popup` |
| BUNDLE_ID | `ETECHFLOW_MAGENTO_BUNDLE_V1` |
| Composer name | `etechflow/module-abandoned-cart` |
| Magento module name | `Etechflow_AbandonedCart` |
| Repo (private) | https://github.com/etechflow/magento-abandoned-cart-email |
| Default Portal URL | `https://license.etechflow.com/license/validate` |

---

## 7. Security Reminders

- **Bundle Secret** + **Per-module SECRET_FRAGMENTS** must stay byte-identical between:
  1. `app/code/Etechflow/AbandonedCart/Model/LicenseValidator.php`
  2. `tools/generate-license.php`
  3. The portal's `license_engine.py` (if it duplicates them)

- Never commit Stripe live keys, portal database, or the secret fragments to a public repo. This module's repo is **private** — keep it that way.

- Rotation requires updating all 3 locations + version bump + key re-issuance for existing customers (see `LICENSING_PROTOCOL.md` §5).

---

*Document version: v1.3.0 — created June 2026 when portal subscription licensing was integrated into Etechflow_AbandonedCart.*
