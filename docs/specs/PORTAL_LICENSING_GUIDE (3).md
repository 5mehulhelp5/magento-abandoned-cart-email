# eTechFlow Portal Licensing — Implementation Guide

**Hand this file to Claude with "apply portal licensing to `<module>` per PORTAL_LICENSING_GUIDE.md" and it will implement the entire system below.**

This is the **runbook** for adding **subscription (SP-XXXX) portal licensing + in-admin Stripe checkout + an admin gate page** to any eTechFlow Magento 2 module. It has been validated end-to-end on `ETechFlow_BackInStockNotification` and `ETechFlow_DeliveryDate` on Magento 2.4.9.

## How this relates to the other docs

- **`LICENSING_PROTOCOL.md`** — the HMAC-SHA256 per-module + bundle key protocol. **Still fully in force.** This guide does **not** replace it; it **layers on top**. Keep `MODULE_ID`, `SECRET_FRAGMENTS`, `BUNDLE_ID`, `BUNDLE_SECRET_FRAGMENTS`, and `XML_PATH_BUNDLE_LICENSE_KEY` **byte-identical** to what the protocol mandates. The HMAC per-module key and the bundle key must keep working.
- **`MODULE_STANDARDS.md`** — the code standards (strict_types, readonly DI, gate every entry point, no ObjectManager, unit tests, idempotent patches). **All still apply.** Every file produced here must follow them.

In short: **HMAC keys (per-module + bundle) keep working for offline/bundle activation; SP-XXXX keys add a paid, portal-validated subscription with a domain + server-IP check, Stripe self-checkout, and IP-block auto-management.**

---

## 1. Architecture

```
                          ┌──────────────────────────────────────────────┐
                          │  eTechFlow Portal (Flask, app.py)            │
                          │  - PostgreSQL `subscriptions` table          │
                          │  - PLANS dict in license_engine.py           │
                          │  - Stripe (keys in .env OR passed by Magento)│
                          └──────────────────────────────────────────────┘
   GET /license/validate ?domain&license_key&platform=magento&module=<id>
        │  strict per-key lookup → active? domain match? IP match?
        │  200 {valid:true,...}  |  403 {valid:false}  |  403 {ip_blocked:true}
        ▼
┌────────────────────────────────────────────────────────────────────────┐
│ Magento module — Model/LicenseValidator.php                            │
│   isValid():                                                            │
│     dev-host?  → true (bypass)                                         │
│     prod_env=No? → true (bypass)                                       │
│     key starts "SP-"? → validateViaPortal() (cached PORTAL_CACHE_TTL)  │
│     else → HMAC per-module key OR bundle key (LICENSING_PROTOCOL.md)   │
│   Config::isEnabled() = isValid() && admin "enabled" flag             │
└────────────────────────────────────────────────────────────────────────┘
        │ gates every entry point (storefront via Config::isEnabled;
        │ admin landing controllers redirect to the gate page)
        ▼
┌────────────────────────────────────────────────────────────────────────┐
│ Admin gate flow (when not licensed):                                   │
│   License/Gate      → dark plan-cards page + "Enter License Key"       │
│   License/Checkout  → reads Stripe key (decrypt) → Stripe Checkout     │
│   Stripe → redirects back → License/Activated → portal /license/activate│
│            → saves SP-XXXX key to config → success page (shows key)    │
└────────────────────────────────────────────────────────────────────────┘
```

**Paid-purchase flow:** Gate → pick plan → name+email → Stripe Checkout (card) → back to Magento → portal verifies payment + issues SP-XXXX key → Magento saves key → module unlocks.

**IP-block auto-management:** portal returns `ip_blocked:true` → Magento clears the key + sets an `ip_blocked` flag; when the IP is restored the portal returns valid and Magento restores the key from `issued_key`. A **manual** key clear is NOT auto-restored (the flag distinguishes the two).

---

## 2. Per-module values to decide first

Pick these once per module (examples from the two shipped modules):

| Token | BISN value | DeliveryDate value | Rule |
|---|---|---|---|
| Module name | `ETechFlow_BackInStockNotification` | `ETechFlow_DeliveryDate` | existing |
| Namespace | `ETechFlow\BackInStockNotification` | `ETechFlow\DeliveryDate` | existing |
| Composer pkg | `etechflow/module-back-in-stock-notification` | `etechflow/module-delivery-date` | existing |
| Config section id | `etechflow_bisn` | `etechflow_deliverydate` | existing (from system.xml) |
| Admin route frontName | `etechflow_bisn` | `etechflow_dd` | existing (from adminhtml/routes.xml) |
| Portal `MODULE_ID` | `back-in-stock-notification` | `delivery-date` | = existing HMAC MODULE_ID |
| Cache tag | `ETECHFLOW_BISN` | `ETECHFLOW_DD` | unique per module |
| Cache prefix | `etf_bisn_lic_` | `etf_dd_lic_` | unique per module |
| Plan slugs | `bisn_starter/professional/enterprise` | `dd_starter/professional/enterprise` | `<short>_<tier>` |
| Plan prices (USD) | 19 / 49 / 99 | 15 / 39 / 79 | your call |
| Admin landing controllers to gate | `Subscription/Index` | `TimeInterval/Index`, `ExceptionDay/Index` | the module's grid index pages |
| Gate redirect target | `etechflow_bisn/subscription/index` | `etechflow_dd/timeInterval/index` | the module's main admin page |

**Before writing code, read the module's existing:** `etc/adminhtml/routes.xml` (route frontName), `etc/adminhtml/system.xml` (section id + existing license group), `etc/adminhtml/menu.xml` + admin controllers (what to gate), and `Model/LicenseValidator.php` (existing `MODULE_ID` + `SECRET_FRAGMENTS` — **keep them**).

---

## 3. Implementation steps

### Step 1 — `Model/LicenseValidator.php` (rewrite, hybrid HMAC + portal)

Constructor goes from 2 args to **5**: `ScopeConfigInterface, StoreManagerInterface, CacheInterface, Curl, WriterInterface`.

**Preserve unchanged:** `MODULE_ID`, `SECRET_FRAGMENTS`, `BUNDLE_ID`, `BUNDLE_SECRET_FRAGMENTS`, `XML_PATH_BUNDLE_LICENSE_KEY`, `XML_PATH_LICENSE_KEY`, `XML_PATH_PRODUCTION_ENVIRONMENT`, and all HMAC methods (`computeKey`, `computeBundleKey`, `canonicalize`).

**Add these config paths** (swap `<section>`):
```php
public const XML_PATH_ISSUED_KEY  = '<section>/license/issued_key';
public const XML_PATH_ISSUED_AT   = '<section>/license/issued_at';
public const XML_PATH_IP_BLOCKED  = '<section>/license/ip_blocked';
public const XML_PATH_PORTAL_URL  = '<section>/license/portal_url';
```

**Add constants:**
```php
private const DEFAULT_PORTAL_URL   = 'https://<current-ngrok>.ngrok-free.dev/license/validate';
public  const PORTAL_CACHE_TTL     = 30;   // valid result cache (s) — suspensions apply within this window
public  const PORTAL_CACHE_TTL_BAD = 60;   // invalid result cache (s) — re-check quickly after re-activation
private const CACHE_TAG    = 'ETECHFLOW_<MOD>';
private const CACHE_PREFIX = 'etf_<mod>_lic_';
```

**`isValid()`** = dev-host bypass → prod-env bypass → `checkKey($host)`.

**`checkKey($host)`** logic (the core):
```php
$configuredKey = $this->getConfiguredKey();
$isEmptyKey    = ($configuredKey === '');
if ($isEmptyKey) {
    // Only fall back to issued_key when an IP-block event cleared it.
    // Manual clear (ip_blocked != 1) keeps the module LOCKED.
    if ((int)$this->scopeConfig->getValue(self::XML_PATH_IP_BLOCKED) !== 1) return false;
    $configuredKey = trim((string)$this->scopeConfig->getValue(self::XML_PATH_ISSUED_KEY));
    if ($configuredKey === '') return false;
}
if (str_starts_with($configuredKey, 'SP-')) {
    if (!$isEmptyKey && $this->isLocallyIssuedKey($configuredKey, $host)) return true; // 48h grace
    $valid = $this->validateViaPortal($host, $configuredKey);
    if ($valid && $isEmptyKey) $this->writeLicenseKey($configuredKey);  // restore after IP unblock
    return $valid;
}
// HMAC path (LICENSING_PROTOCOL.md): per-module key, then bundle key
if ($configuredKey !== '' && hash_equals($this->computeKey($host), $configuredKey)) return true;
$bundleKey = $this->getConfiguredBundleKey();
return $bundleKey !== '' && hash_equals($this->computeBundleKey($host), $bundleKey);
```

**`validateViaPortal($host, $key)`:**
- Cache key = `CACHE_PREFIX . md5($host.':'.$key)`. Return cached `'1'/'0'` on hit.
- Build URL via `getPortalUrl()` + `?domain=&license_key=&platform=magento&module=<MODULE_ID>`.
- `Curl` GET, 10s timeout, `Accept: application/json`. **Wrap in try/catch — on exception return false WITHOUT caching** (portal unreachable shouldn't hard-cache a deny).
- `200` + `valid:true` → valid. `403` + `ip_blocked:true` → set `$ipBlocked`.
- Cache result: `PORTAL_CACHE_TTL` if valid else `PORTAL_CACHE_TTL_BAD`, tagged `CACHE_TAG`.
- On first valid result with empty `issued_key`: save `issued_key` + `issued_at = time()` (for IP-block restore + 48h grace), then `cache->clean([Config::CACHE_TAG])`.
- If `$ipBlocked`: call `clearLicenseKey()`.

**`clearLicenseKey()`** (public): if current key non-empty → save `''` to `XML_PATH_LICENSE_KEY`, save `'1'` to `XML_PATH_IP_BLOCKED`, `cache->clean([Config::CACHE_TAG])`. **`writeLicenseKey($key)`**: save key, save `'0'` to `XML_PATH_IP_BLOCKED`, clean config cache.

**`isLocallyIssuedKey()`**: `issued_at===0 → false`; `time()-issued_at > 172800 → false` (48h); else `issued_key === $key`.

**`getPortalUrl()`**: config `XML_PATH_PORTAL_URL` else `DEFAULT_PORTAL_URL`.

**`isDevelopmentHost()`**: keep the standard list BUT **remove the `-(staging|stage|dev|...)\.` hyphen-regex** (it false-matches prod domains like `magento-dev.etechflow.com`, blocking the portal check) and **add `.ngrok-free.dev`** to the tunnel suffixes.

> Full reference implementation: see `vendor/etechflow/module-delivery-date/Model/LicenseValidator.php` after this guide is applied. Copy it and swap the per-module tokens from §2.

### Step 2 — `etc/config.xml`

Add under `<<section>><license>`: `<issued_key/>`, `<issued_at>0</issued_at>`, `<ip_blocked>0</ip_blocked>`, `<portal_url>https://<ngrok>.ngrok-free.dev/license/validate</portal_url>`. Add a `<payment>` group: `<stripe_secret_key/>`, `<stripe_publishable_key/>`, `<stripe_currency>usd</stripe_currency>`. Keep all existing groups.

### Step 3 — `etc/adminhtml/system.xml`

In the existing `license` group, make:
- `license_key` → `type="text"` (plain, paste SP-XXXX or bundle key)
- `issued_key` → `type="obscure"` + `backend_model Encrypted`, `showInWebsite/Store="0"`
- `portal_url` → `type="text"`
- `bundle_license_key` → `type="obscure"` + `backend_model Encrypted`, `config_path=etechflow_bundle/license/license_key`
- `production_environment` → `type="select"` Yesno

Add a **`payment`** group (Stripe): `stripe_secret_key` (`obscure` + `Encrypted`), `stripe_publishable_key` (`text`), `stripe_currency` (`text`).

⚠️ **Do NOT use `Magento\Config\Model\Config\Source\Email\Template` as a source_model** for any field — on Magento 2.4.9 it throws `UnexpectedTemplateIdValueException` (Luma theme variants like `..._template/Magento/luma` can't resolve a label) and **crashes the whole config page**. Use a plain `text` field for email-template IDs.

### Step 4 — three controllers under `Controller/Adminhtml/License/`

All three: `public const ADMIN_RESOURCE = '<Module>::config';`, extend `Magento\Backend\App\Action`.

- **`Gate.php`** — if `licenseValidator->isValid()` redirect to the module's main admin page; else render the gate page (PageFactory).
- **`Checkout.php`** — POST `plan,name,email`. Read `stripe_secret_key` from config and **decrypt it** via `EncryptorInterface->decrypt()` (it's stored encrypted!). Build a Stripe Checkout Session via **direct cURL** to `https://api.stripe.com/v1/checkout/sessions` (`Authorization: Bearer <key>`, form-encoded `price_data` with plan amount in cents, `success_url` = `<route>/license/activated?session_id={CHECKOUT_SESSION_ID}&plan=&domain=&name=&email=`, `cancel_url` = gate). Redirect browser to `$data['url']`.
- **`Activated.php`** — read `session_id` etc. Decrypt the Stripe key. POST JSON to `portal + /license/activate` with `{session_id, stripe_secret_key, domain, name, email, plan}`. On `200` + `license_key`: save it to `XML_PATH_LICENSE_KEY`, clean config cache, render success page (set block data: `license_key, plan, error, settings_url, management_url`). On error: render the error variant.

**Critical:** `execute()` is typed `: ResultInterface`. **Never `return $this->_redirect(...)`** (returns a `Response`, not `ResultInterface` → `TypeError`). Always:
```php
return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('<route>/license/gate');
```
**Critical:** derive the portal base with `str_replace('/license/validate', '', $portalUrl)` — **never `rtrim($url, '/license/validate')`** (rtrim treats the arg as a char-set and eats `.dev` etc.).

### Step 5 — gate + success views

- `view/adminhtml/layout/<route>_license_gate.xml` → block `Magento\Backend\Block\Template`, template `<Module>::license/gate.phtml`, name `etechflow.<mod>.license.gate`, with `<update handle="styles"/>`.
- `view/adminhtml/layout/<route>_license_activated.xml` → same pattern, block name `etechflow.<mod>.license.activated`.
- `view/adminhtml/templates/license/gate.phtml` — dark navy page: header, **"Select Plan & Pay" / "Enter License Key"** buttons, 3 plan cards (Starter/Professional/Enterprise), a hidden checkout form (name+email + `<?= $block->getBlockHtml('formkey') ?>`) that POSTs to `<route>/license/checkout`. JS `etfShowForm(plan,name,price)` reveals the form. (Per MODULE_STANDARDS the gate is admin-only, so inline `<style>` is acceptable here — it never ships to the storefront/CSP surface.)
- `view/adminhtml/templates/license/activated.phtml` — success card with the SP-XXXX key + copy button, "flush cache" note, and links to the module + settings; error variant when no key.

> Copy the gate/activated phtml from a shipped module and swap labels, plan names/prices, route, and the "Enter License Key" target section id.

### Step 6 — gate every entry point (MODULE_STANDARDS §4-D)

- **Storefront:** already covered — every storefront surface checks `Config::isEnabled()`, which calls `isValid()`. No change needed beyond Step 1.
- **Admin landing controllers:** in each grid `Index` controller, inject `LicenseValidator` (add a constructor that calls `parent::__construct($context)`) and at the top of `execute()`:
```php
if (!$this->licenseValidator->isValid()) {
    return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('<route>/license/gate');
}
```

### Step 7 — portal side (one-time per module)

In `shopify-portal/license_engine.py`, add the module's plans to the `PLANS` dict:
```python
'<mod>_starter':      {'name': '<Module> Starter',      'price_monthly': 15, 'module': '<MODULE_ID>', ...},
'<mod>_professional': {'name': '<Module> Professional', 'price_monthly': 39, 'module': '<MODULE_ID>', ...},
'<mod>_enterprise':   {'name': '<Module> Enterprise',   'price_monthly': 79, 'module': '<MODULE_ID>', ...},
```
The portal endpoints are **module-agnostic and already exist** — no per-module endpoint work:
- `GET /license/validate` — strict per-key lookup (`_find_active_subscription(domain, license_key)`): when an `SP-` key is given it looks up **by key only**; if that subscription isn't `active` it returns `403` and does **not** fall back to a domain match. Adds `ip_blocked:true` on IP-lock.
- `POST /license/activate` — verifies the Stripe session (using the `stripe_secret_key` Magento passes, or the portal's own), activates/creates the subscription, returns `{license_key, plan, allowed_ips, domain}`. Idempotent on `session_id`.
- `POST /payment/stripe/create-session` — supports a `magento_callback` param for the success URL.

**Flask runs with `use_reloader=False` → restart Flask after any portal code edit.** Create a portal subscription row (or let Stripe checkout create it) to test.

---

## 4. Deploy sequence (exact, in order)

```bash
# (inside the magento-app docker container, /var/www/html)
php bin/magento setup:upgrade                       # register new config paths
php bin/magento setup:di:compile                    # MUST pass — see gotcha G
php bin/magento setup:static-content:deploy -f --area=adminhtml -j4
php bin/magento cache:flush
# clear OPcache on the long-running workers (see gotcha H):
pkill -f "php-fpm: pool"     # as root; master respawns fresh workers
```
Then in Magento admin: **Stores → Config → eTechFlow → <Module> → Payment Settings** → enter Stripe `sk_`/`pk_` keys + currency; confirm **License → License Portal URL** matches the current ngrok URL.

---

## 5. Testing checklist

1. **Locked by default** — no key → admin grid redirects to gate; `isValid()` = NO.
2. **Manual key entry** — paste an active SP-XXXX key → flush cache → grid loads; storefront feature active.
3. **Stripe purchase** — gate → plan → name+email → Stripe test card `4242 4242 4242 4242` (any future exp, any CVC) → redirected back → success page shows SP-XXXX key, key auto-saved, module unlocks.
4. **Suspend** (portal) → within `PORTAL_CACHE_TTL` seconds → gate returns (key NOT auto-restored — `ip_blocked` flag is 0).
5. **Re-activate** → within `PORTAL_CACHE_TTL_BAD` seconds → accessible again.
6. **IP block** — change allowed IP in portal → `ip_blocked:true` → key cleared + flag=1 → restore IP → key auto-restored from `issued_key`.
7. **Bundle key** still activates the module (LICENSING_PROTOCOL.md regression).
8. **Dev host** (`*.test`, localhost, ngrok) bypasses licensing.

---

## 6. Gotchas / lessons learned (READ BEFORE IMPLEMENTING)

These are real bugs we hit on Magento 2.4.9. Each one cost a debugging cycle — avoid them up front.

**A. `rtrim($url, '/license/validate')` is a trap.** `rtrim`'s 2nd arg is a character set, not a suffix — it strips `.dev`, etc. Use `str_replace('/license/validate', '', $url)`.

**B. Controller return type.** `execute(): ResultInterface` must NOT `return $this->_redirect(...)` (that returns `Response`). Use `resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath(...)`.

**C. Stripe key is stored ENCRYPTED.** The `obscure` field with `Encrypted` backend model stores `0:3:...`. Always `EncryptorInterface->decrypt()` before sending to Stripe — otherwise "Invalid API Key".

**D. Email-template source model crashes the config page.** Never use `Magento\Config\Model\Config\Source\Email\Template`; use a plain `text` field. (2.4.9 Luma variant IDs throw `UnexpectedTemplateIdValueException`.)

**E. ACL `<resource>` needs a `title`.** A parent `<resource>` without `title` (e.g. referencing `Magento_Sales::operations`) makes the merged ACL invalid → **every admin page 500s**. Parent the module's admin resources under an existing titled node like `Magento_Backend::marketing` (the AbandonedCart pattern).

**F. UI-grid data source must be in ADMINHTML-scope di.xml, not global.** Register the `CollectionFactory` `collections` entry in `etc/adminhtml/di.xml`. If it's only in `etc/di.xml` (global), another module's `etc/adminhtml/di.xml` CollectionFactory definition **overrides** the global `collections` array in the admin area and your handle vanishes → `Not registered handle ...`. Also: never declare the same `<type name="CollectionFactory">` block twice in one file — the second silently replaces the first.

**G. UI grid needs `SearchResult` + `Document` items.** The grid collection virtualType must extend `Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult` (args `mainTable`, `resourceModel`). Returning entity models instead makes the DataProvider call `getCustomAttributes()` on `null` → `foreach() ... null`. Don't hand-roll `getItems()`/`getAll()` (`getAll()` doesn't exist on collections). Use the standard `DataProvider` class in the listing XML, not a custom one. Remove empty `<buttons></buttons>` (comment-only) blocks — 2.4.9 `addButtons()` requires an array and throws on the comment string.

**H. After `di:compile`, restart php-fpm.** OPcache caches the OLD compiled DI in the long-running workers; CLI tests pass (CLI OPcache off) while the browser still errors. `pkill -f "php-fpm: pool"` (master respawns fresh workers). When in doubt, nuke `generated/code/* generated/metadata/* var/cache/* var/di/*` then recompile.

**I. Unit test constructor arity.** Changing `LicenseValidator`'s constructor (2→5 args) breaks `Test/Unit/.../LicenseValidatorTest.php`'s `new LicenseValidator(...)` → `ArgumentCountError` surfaces during `setup:upgrade`. Update the test to mock all 5 deps (add `CacheInterface`, `Curl`, `WriterInterface`; stub `cache->load()` → false).

**J. `ip_blocked` flag is mandatory.** Without it, clearing `license_key` falls back to `issued_key` and silently re-validates → the key "reappears" and the module stays unlocked. The flag (1 = auto IP-block clear, 0 = manual) makes manual clears stay locked.

**K. Strict per-key portal lookup.** When an `SP-` key is provided, the portal must look up by key only and fail if that sub isn't active — never fall back to a domain match (a suspended key would otherwise validate via another active sub on the same domain). Applies in both `_find_active_subscription` and the validate endpoint's legacy fallback guard.

**L. ngrok free URL changes on restart.** The `portal_url` config (and `DEFAULT_PORTAL_URL`) must be updated when the tunnel URL changes; otherwise "Could not resolve host". For stability use a fixed ngrok domain or run the portal on the same host as Magento.

**M. Flask `use_reloader=False`.** Restart Flask after any portal (`app.py` / `license_engine.py`) edit.

---

## 7. Per-module quick checklist

```
[ ] Read existing module: routes.xml frontName, system.xml section id, menu.xml, admin controllers, LicenseValidator (keep MODULE_ID + SECRET_FRAGMENTS)
[ ] LicenseValidator.php — 5-arg ctor, portal validate, ip_blocked flag, issued_key fallback, drop hyphen-dev regex, add .ngrok-free.dev, cache tag/prefix unique
[ ] config.xml — issued_key, issued_at, ip_blocked, portal_url, payment.stripe_*
[ ] system.xml — license group (text key, obscure issued_key, portal_url, obscure bundle key, prod toggle) + payment(Stripe) group; NO Email\Template source_model
[ ] Controller/Adminhtml/License/{Gate,Checkout,Activated}.php (ResultFactory redirects; decrypt Stripe key; str_replace portal base)
[ ] view/adminhtml/layout/<route>_license_{gate,activated}.xml + templates/license/{gate,activated}.phtml
[ ] Gate each admin grid Index controller (inject LicenseValidator → redirect to <route>/license/gate)
[ ] etc/adminhtml/di.xml — grid CollectionFactory collections + SearchResult virtualTypes (ADMIN scope!)
[ ] Update Test/Unit LicenseValidatorTest to 5-arg ctor
[ ] Portal: add <mod>_{starter,professional,enterprise} plans to license_engine.py PLANS; restart Flask
[ ] Deploy: setup:upgrade → di:compile (must pass) → static deploy → cache:flush → pkill php-fpm pool
[ ] Enter Stripe keys + confirm portal_url in admin
[ ] Run the §5 testing checklist
[ ] (optional) bump composer.json + module.xml version; push to GitHub → Packagist auto-syncs
```

---

*Companion to `LICENSING_PROTOCOL.md` (HMAC/bundle protocol) and `MODULE_STANDARDS.md` (code standards). Validated on ETechFlow_BackInStockNotification v1.1.0 and ETechFlow_DeliveryDate on Magento 2.4.9. Keep this file with the other two specs.*
