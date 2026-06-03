# eTechFlow Licensing Protocol — Standard for All Modules

**Required reading before adding licensing to any new eTechFlow module.** Read this first — then refer to `MODULE_STANDARDS.md` for the full code standards.

This document is the **single source of truth** for how licensing works across:
- Every Magento module we ship
- The webstore (etechflow.com) that generates keys
- The `tools/generate-license.php` CLI generator

If you deviate from this protocol, your module won't integrate with our webstore — customers will buy and not get a working module. **Don't deviate.**

---

## 1. The protocol in one paragraph

Every eTechFlow module validates licenses by computing **HMAC-SHA256(canonical_host : module_id, secret)** and comparing against the key stored in admin. Our webstore generates keys using the **exact same algorithm + same secrets**. As long as your new module follows this protocol exactly, keys minted by the webstore will validate inside your module. Diverge from any of the four constants (module_id, per-module secret, bundle_id, bundle secret) and the customer's key won't work.

---

## 2. Why we use HMAC instead of random keys

If your module accepted random keys, you'd need either:
1. A central server the module calls to validate (phone-home — slow, customer firewalls block it, you maintain it forever)
2. A list of valid keys baked in (impossible — we don't know future customers)
3. No validation at all (no piracy protection)

HMAC solves this: the math itself proves the key was minted by us for that specific domain. **No internet call required.** Every module vendor (Magefan, Amasty, MageWorx) uses this same model.

---

## 3. The protocol contract — what every module MUST implement

Every new module has a `Model/LicenseValidator.php` with these constants:

| Constant | Per-module value | Shared across all modules |
|---|---|---|
| `MODULE_ID` | Unique slug for THIS module (e.g. `next-day-eligibility`) | ❌ Unique per module |
| `SECRET_FRAGMENTS` | Array of 4 random strings, unique to THIS module | ❌ Unique per module |
| `XML_PATH_LICENSE_KEY` | Per-module admin config path (e.g. `etechflow_<module>/license/license_key`) | ❌ Unique per module |
| `XML_PATH_PRODUCTION_ENVIRONMENT` | Per-module path (e.g. `etechflow_<module>/license/production_environment`) | ❌ Unique per module |
| `BUNDLE_ID` | `'etechflow-bundle'` | ✅ **MUST be identical** in every module |
| `BUNDLE_SECRET_FRAGMENTS` | The 4 bundle secret fragments | ✅ **MUST be identical** in every module |
| `XML_PATH_BUNDLE_LICENSE_KEY` | `'etechflow_bundle/license/license_key'` | ✅ **MUST be identical** in every module |

**The bundle constants are the load-bearing cryptographic glue.** Every module shares them so that one bundle key activates all modules. If you change any bundle constant in one module without changing it in every other module + the webstore generator, the bundle key silently breaks.

**`XML_PATH_PRODUCTION_ENVIRONMENT` is per-module** because each module's admin config has its own License section. The toggle behaviour must be IDENTICAL across modules (default true on null/empty; treat "0" as false; treat "1" as true) — but the config path itself is per-module.

---

## 4. New module licensing checklist

Follow this when building any new eTechFlow module. Tick each box before merging.

### ☐ A. Decide your module's slug and admin config path

| Item | Format | Example |
|---|---|---|
| Module slug (the `MODULE_ID`) | kebab-case, descriptive | `inventory-syncer`, `customer-loyalty` |
| Admin config root | `etechflow_<modulename>/...` | `etechflow_inventorysyncer/license/license_key` |
| Composer package name | `etechflow/module-<kebab>` | `etechflow/module-inventory-syncer` |

The slug appears in:
- `LicenseValidator::MODULE_ID`
- The CLI generator's `--module=<slug>` argument
- The webstore's order metadata

### ☐ B. Generate 4 unique random secret fragments

Run this command to generate cryptographically random fragments:

```bash
for i in 1 2 3 4; do echo "  '$(openssl rand -base64 12 | tr -d '/+=' | cut -c1-12)'"; done
```

Output (yours will differ):

```
  'p3K8mN2qXyZ1'
  'aB4cE9gH7jLm'
  '5kRtY6uVwS8z'
  'F2nQ3rT9bA4c'
```

These are your module's `SECRET_FRAGMENTS`. **Save them only inside `LicenseValidator.php`** — nowhere else. Never log them, never paste them in chat, never commit them to a public repo.

### ☐ C. Copy LicenseValidator.php from an existing module

The cleanest way is to copy `app/code/ETechFlow/NextDayEligibility/Model/LicenseValidator.php` and modify only these three things:

| Change | From | To |
|---|---|---|
| Class namespace | `namespace ETechFlow\NextDayEligibility\Model;` | `namespace ETechFlow\YourModule\Model;` |
| `XML_PATH_LICENSE_KEY` | `'etechflow_nextdayeligibility/...'` | `'etechflow_yourmodulename/...'` |
| `MODULE_ID` | `'next-day-eligibility'` | `'your-module-slug'` |
| `SECRET_FRAGMENTS` | the existing 4 strings | your 4 NEW random strings from step B |

**Leave these alone — they must NEVER change:**
- `BUNDLE_ID`
- `BUNDLE_SECRET_FRAGMENTS`
- `XML_PATH_BUNDLE_LICENSE_KEY`
- All public methods (`isValid()`, `computeKey()`, `computeBundleKey()`, `canonicalize()`, `getCurrentHost()`)
- The private `isDevelopmentHost()` method (must recognize the same dev hosts)

### ☐ D. Add license gating to every entry point in your module

Anywhere your module does work that's visible to customers (badges, restrictions, observers, plugins, ViewModels, ConfigProviders), gate it:

```php
public function execute(Observer $observer): void
{
    if (!$this->config->isEnabled()) {
        return;  // Silently no-op when license invalid or module disabled
    }
    // ... real work ...
}
```

`Config::isEnabled()` calls both the admin enabled flag AND the license validator. Missing this on any entry point creates a license bypass — pirates can find that one ungated path.

### ☐ E. Add the per-module admin config (License section)

In `etc/adminhtml/system.xml`, add a "License" group with one field:

```xml
<group id="license" translate="label" type="text" sortOrder="100" showInDefault="1" showInWebsite="1" showInStore="1">
    <label>License</label>
    <field id="license_key" translate="label comment" type="text" sortOrder="10" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>License Key</label>
        <comment>Paste your eTechFlow license key. Get one at https://etechflow.com</comment>
    </field>
</group>
```

The path must match `LicenseValidator::XML_PATH_LICENSE_KEY` — e.g. `etechflow_<yourmodulename>/license/license_key`.

### ☐ F. Register your module in `tools/generate-license.php`

The CLI generator needs to know about your new module. Add an entry to the `MODULES` constant in `tools/generate-license.php`:

```php
const MODULES = [
    'next-day' => [...],
    'eta' => [...],
    'backorder' => [...],
    'bundle' => [...],
    
    // ADD YOUR MODULE
    'inventory-syncer' => [
        'id'        => 'inventory-syncer',
        'fragments' => ['p3K8mN2qXyZ1', 'aB4cE9gH7jLm', '5kRtY6uVwS8z', 'F2nQ3rT9bA4c'],
        'label'     => 'ETechFlow_InventorySyncer',
        'admin'     => 'Inventory Syncer',
        'config'    => 'etechflow_inventorysyncer/license/license_key',
    ],
];
```

**Use the same 4 fragments and the same slug as your `LicenseValidator.php`.** If they drift, the CLI generator produces keys that don't validate inside your module.

### ☐ G. Register your module in `docs/license.ts.example` (webstore)

Same pattern — the webstore's TypeScript generator needs the same entry. Add to the `MODULES` constant in `docs/license.ts.example`:

```typescript
'inventory-syncer': {
  id: 'inventory-syncer',
  fragments: [
    process.env.ETF_INV_FRAGMENT_1 ?? 'p3K8mN2qXyZ1',
    process.env.ETF_INV_FRAGMENT_2 ?? 'aB4cE9gH7jLm',
    process.env.ETF_INV_FRAGMENT_3 ?? '5kRtY6uVwS8z',
    process.env.ETF_INV_FRAGMENT_4 ?? 'F2nQ3rT9bA4c',
  ],
  label: 'ETechFlow_InventorySyncer',
},
```

The webstore loads fragments from `.env` in production, falls back to the hardcoded values in dev. **Same 4 fragments + same slug across all 3 places** (PHP module, PHP CLI, TS webstore).

### ☐ H. Add unit tests for the validator

Copy `app/code/ETechFlow/NextDayEligibility/Test/Unit/Model/LicenseValidatorTest.php` and adapt the namespace + module-specific assertions. Confirms:

- Per-module key validates correctly
- Bundle key activates the module (via shared bundle secret)
- Dev hosts auto-bypass
- Wrong keys silently fail

If your new tests pass, your licensing implementation is correct.

### ☐ I. Run the consistency check

After all the above, run this script from the repo root to confirm bundle constants match across modules + the CLI generator:

```bash
php tools/verify-bundle-consistency.php
```

(If this script doesn't exist yet, create it with the snippet at the end of this doc — see Appendix A.)

Expected output: **"✓ All modules + CLI share the same bundle secret + ID + config path."**

If it fails, your bundle constants drifted. Fix before merging.

---

## 5. Coordinating with the team

### Adding a new module — checklist before announcing it

| Step | Done? |
|---|---|
| `LicenseValidator.php` written in your module with unique secret + module_id | ☐ |
| `etc/adminhtml/system.xml` has License section with the right config path | ☐ |
| Every observer/plugin/block/ViewModel gated by `Config::isEnabled()` | ☐ |
| `tools/generate-license.php` updated with your module entry | ☐ |
| `docs/license.ts.example` updated with your module entry | ☐ |
| Unit tests pass (`vendor/bin/phpunit app/code/ETechFlow/YourModule/Test/Unit`) | ☐ |
| Bundle-consistency check passes | ☐ |
| Updated `docs/CUSTOMER_LICENSE_GUIDE.md` if customer-facing licensing differs | ☐ |
| Updated `docs/WEBSTORE_INTEGRATION_BRIEF.md` to mention the new module + slug | ☐ |
| Updated the webstore's product catalog with the new module + slug | ☐ |

### Secret rotation — when and how

If a secret leaks (someone publishes it, source code stolen, etc.):

1. Generate new fragments for the affected module (Section 4-B)
2. Update **all three** places: `LicenseValidator.php`, `tools/generate-license.php`, `docs/license.ts.example`
3. **Bump the module's version** (a version bump signals existing customers to update)
4. Re-issue keys for all existing customers from the audit trail in `tools/licenses.csv`
5. Email all affected customers with the new key + module version
6. Update `docs/INTERNAL_NOTES.md` with the rotation date + reason

**Bundle secret rotation** is exponentially more painful — it forces re-issuing keys for every bundle customer across all 3 modules. Avoid leaking the bundle secret.

---

## 6. Common mistakes to avoid

| Mistake | What goes wrong |
|---|---|
| Generating random keys instead of HMAC | Customer pastes key, module silently no-ops, support ticket flood |
| Using the same secret across modules | Customer's NDE key would activate ETA too — pricing model breaks |
| Forgetting to update `tools/generate-license.php` after adding a module | CLI generator can't mint keys for your new module |
| Forgetting to update `docs/license.ts.example` | Webstore can't mint keys for your new module → can't actually sell it |
| Changing `BUNDLE_*` constants in one module only | Bundle key silently stops working in that module |
| Skipping `Config::isEnabled()` gating on a plugin/observer | Pirates find the ungated path, license bypass |
| Using `getValue($path, ScopeInterface::SCOPE_DEFAULT)` instead of `SCOPE_STORE` | Multi-store merchants can't use different keys per store view |
| Logging full license keys in plaintext | Audit risk if logs leak |
| Hard-time-bombing the license key with `expires_at` field | Customer's subscription card fails → checkout breaks → 1-star review. Use soft model. |
| Calling our license server from inside the module (phone-home) | Customer firewall blocks outbound → module breaks. Don't phone home. |
| Encoding `LicenseValidator.php` with ionCube but forgetting to ship the loader instructions | Customer's PHP can't load → silent failure |

---

## 7. The architecture diagram

```
┌──────────────────────────────────────────────────────────────────────┐
│ WEBSTORE                                                             │
│                                                                       │
│  When customer buys:                                                  │
│    src/lib/orders/license.ts → generateLicenseKey(module, domain)    │
│      ↓                                                                │
│      HMAC-SHA256(canonicalize(domain) + ":" + module_id, secret)     │
│      ↓                                                                │
│    → returns key (e.g. "R7yHzQppEZmJTFldoiR15...")                  │
│                                                                       │
│  Email customer the key + download link                              │
│                                                                       │
└──────────────────────────────────────────────────────────────────────┘
                              │
                              │  Customer pastes key
                              ▼
┌──────────────────────────────────────────────────────────────────────┐
│ CUSTOMER'S MAGENTO                                                   │
│                                                                       │
│  On every page load:                                                  │
│    app/code/ETechFlow/<Module>/Model/LicenseValidator.php             │
│      ↓                                                                │
│      isValid() → computes expected key for current store's host       │
│                  using SAME algorithm + SAME secret as webstore       │
│      ↓                                                                │
│    → if match: module activates                                       │
│      if not:   module silently no-ops                                 │
│                                                                       │
└──────────────────────────────────────────────────────────────────────┘
                              │
                              │  Both sides use:
                              ▼
                  ┌─────────────────────────┐
                  │  SHARED ALGORITHM        │
                  │  + SHARED SECRETS        │
                  │                          │
                  │  This is the protocol.   │
                  │  Don't break it.         │
                  └─────────────────────────┘
```

---

## 8. The four files that MUST stay in sync

| File | Contains | If it drifts… |
|---|---|---|
| `app/code/ETechFlow/<Module>/Model/LicenseValidator.php` | Per-module secret + bundle secret + module_id | Module won't validate keys |
| `tools/generate-license.php` | All modules' secrets + bundle secret | CLI can't mint working keys |
| `docs/license.ts.example` (deployed as `src/lib/orders/license.ts` in webstore) | All modules' secrets + bundle secret | Webstore can't mint working keys |
| `app/code/ETechFlow/<Module>/Test/Unit/Model/LicenseValidatorTest.php` | Test cases | Validates the above are correctly wired |

When you add a new module, update **all four**. When you rotate a secret, update **all four**. When you change anything bundle-related, update **all four**.

---

## 9. Customer-facing rules — what every module gets for free

Inheriting from this protocol automatically gives the customer:

- **Free dev/staging trial** — any `*.test`, `*.local`, `staging.*`, `*.magento.cloud`, RFC 1918 IP, ngrok tunnel automatically bypasses licensing
- **Domain canonicalization** — `www.coolstore.com` and `coolstore.com` are treated as the same site (one key works for both)
- **Soft expiry** — subscription lapses don't break the module; just stop updates/support
- **Per-installation licensing** — one key per Magento install, unlimited dev/staging environments
- **Bundle support** — customer can buy your module standalone OR as part of the eTechFlow bundle (if you've registered it in the bundle's `composer.json`)

You don't need to implement any of this. The standard `LicenseValidator.php` does it all.

---

## 10. Self-test before shipping

After everything is in place, mint a test key for your local dev install and confirm it validates:

```bash
# Step 1: mint a key for your local dev host
php tools/generate-license.php \
    --module=<your-slug> \
    --domain=<your-test-domain> \
    --quiet

# Output: a key string

# Step 2: paste the key in admin
# Stores → Configuration → eTechFlow → <Your Module> → License → License Key

# Step 3: confirm the module activates
# (look for badges, behavior, whatever your module does)
```

If it works on a non-bypass production-style domain, your licensing is correctly integrated.

For full integration testing, also mint a **bundle key** for the same domain and verify your module activates from the bundle key alone (no per-module key needed):

```bash
php tools/generate-license.php --module=bundle --domain=<your-test-domain> --quiet
```

Paste the bundle key in your module's License Key field. Your module should activate. If it doesn't, your `BUNDLE_*` constants don't match the other modules.

---

## Appendix A — Bundle consistency check script

Save this as `tools/verify-bundle-consistency.php`:

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

$base = __DIR__ . '/../app/code/ETechFlow';
$modules = array_filter(glob("$base/*"), 'is_dir');

$signatures = [];

foreach ($modules as $modulePath) {
    $module = basename($modulePath);
    if ($module === '_bundle') continue;
    
    $file = "$modulePath/Model/LicenseValidator.php";
    if (!is_file($file)) continue;
    
    $contents = file_get_contents($file);
    
    preg_match('/BUNDLE_SECRET_FRAGMENTS\s*=\s*\[(.*?)\];/s', $contents, $m);
    if (!isset($m[1])) continue;
    $secret = trim($m[1]);
    
    preg_match("/BUNDLE_ID\s*=\s*'([^']+)'/", $contents, $idMatch);
    $bundleId = $idMatch[1] ?? 'NOT FOUND';
    
    preg_match("/XML_PATH_BUNDLE_LICENSE_KEY\s*=\s*'([^']+)'/", $contents, $pathMatch);
    $configPath = $pathMatch[1] ?? 'NOT FOUND';
    
    $signatures[$module] = [
        'secret_hash' => md5($secret),
        'bundle_id'   => $bundleId,
        'config_path' => $configPath,
    ];
}

$hashes = array_unique(array_column($signatures, 'secret_hash'));
$ids    = array_unique(array_column($signatures, 'bundle_id'));
$paths  = array_unique(array_column($signatures, 'config_path'));

if (count($hashes) === 1 && count($ids) === 1 && count($paths) === 1) {
    echo "✓ All " . count($signatures) . " modules share the same bundle secret + ID + config path.\n";
    exit(0);
} else {
    echo "✗ MISMATCH detected — bundle key will silently break:\n";
    foreach ($signatures as $mod => $sig) {
        echo "  $mod:\n";
        echo "    secret_hash: {$sig['secret_hash']}\n";
        echo "    bundle_id:   {$sig['bundle_id']}\n";
        echo "    config_path: {$sig['config_path']}\n";
    }
    exit(1);
}
```

Run after any licensing change:

```bash
php tools/verify-bundle-consistency.php
```

Add it as a pre-commit git hook for safety.

---

## Appendix B — What this doc replaces

This is the **single source of truth** for licensing across modules. Reference it from:

| Other doc | Should link here |
|---|---|
| `docs/MODULE_STANDARDS.md` § 4 (Licensing) | ✓ Refer devs to this doc for the protocol details |
| `docs/WEBSTORE_INTEGRATION_BRIEF.md` § 5 (How key validates) | ✓ Refer devs to this doc for how the module side works |
| `docs/INTERNAL_NOTES.md` | ✓ Reference this when explaining why we use HMAC |
| Onboarding new developers | ✓ This is required reading |

---

## When in doubt

- **Match an existing module's licensing setup exactly.** Don't try to be clever.
- **Run the consistency check** before merging any change to licensing code.
- **Ask before rotating a secret** — coordinate with the whole team because it cascades.
- **The webstore and the modules must agree on every detail.** If they don't, customers pay and nothing works.

---

*Maintained by the eTechFlow core team. Last updated when v1.0.1 of the three-module suite shipped. If you change a licensing constant or pattern, update this doc + tell the team via the team chat.*
