<?php
/**
 * Etechflow_AbandonedCart - License key minting tool (v1.3.0).
 *
 * Mints HMAC license keys for offline/manual sales. Supports:
 *   --module=<slug>   Per-module key (default: abandoned-cart-popup)
 *   --module=bundle   Bundle key that activates all ETechFlow modules
 *   --domain=<host>   Storefront domain to bind the key to
 *
 * Output is paste-ready for the License Key field. Per LICENSING_PROTOCOL.md
 * §4-F the SECRET_FRAGMENTS + BUNDLE constants here MUST stay byte-identical
 * to LicenseValidator.php and the webstore's license generator.
 *
 * INTERNAL TOOL — NEVER ship this inside the customer-facing zip
 * (the release build excludes `tools/`).
 *
 * Usage:
 *   php tools/generate-license.php --domain=shop.example.com
 *   php tools/generate-license.php --module=bundle --domain=shop.example.com
 *   php tools/generate-license.php --module=abandoned-cart-popup --domain=shop.example.com
 *
 *   Legacy positional form still works:
 *     php tools/generate-license.php shop.example.com
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

const BUNDLE_SECRET = '***REDACTED-BUNDLE-SECRET***';
const BUNDLE_ID     = 'ETECHFLOW_MAGENTO_BUNDLE_V1';

/**
 * Per-module config — slug, secret fragments, friendly label.
 * Keep IN SYNC with each module's LicenseValidator.php.
 */
const MODULES = [
    'abandoned-cart-popup' => [
        'id'        => 'abandoned-cart-popup',
        'label'     => 'ETechFlow_AbandonedCart',
        'fragments' => ['***REDACTED-FRAGMENT-1***', '***REDACTED-FRAGMENT-2***', '***REDACTED-FRAGMENT-3***', '***REDACTED-FRAGMENT-4***'],
        'config'    => 'etechflow_abandoned_cart/license/key',
    ],
    'bundle' => [
        'id'        => 'BUNDLE',
        'label'     => 'ETechFlow Bundle (all modules)',
        'fragments' => null,
        'config'    => 'etechflow_bundle/license/license_key',
    ],
];

/* ---------------- Parse CLI args ---------------- */

$module = 'abandoned-cart-popup';
$host   = '';

foreach (array_slice($argv, 1) as $arg) {
    if (in_array($arg, ['-h', '--help'], true)) {
        printHelp();
        exit(0);
    }
    if (str_starts_with($arg, '--module=')) {
        $module = substr($arg, 9);
        continue;
    }
    if (str_starts_with($arg, '--domain=')) {
        $host = substr($arg, 9);
        continue;
    }
    if ($host === '') {
        // Legacy positional argument
        $host = $arg;
    }
}

if ($host === '') {
    fwrite(STDERR, "Error: --domain=<host> is required.\n\n");
    printHelp();
    exit(1);
}

if (!isset(MODULES[$module])) {
    fwrite(STDERR, "Error: Unknown module '$module'.\n\nAvailable modules:\n");
    foreach (MODULES as $slug => $cfg) {
        fwrite(STDERR, "  $slug  ({$cfg['label']})\n");
    }
    exit(1);
}

/* ---------------- Canonicalize host ---------------- */

$host = strtolower(trim($host));
$host = (string) preg_replace('/^https?:\/\//', '', $host);
$host = (string) preg_replace('/:\d+$/', '', $host);
$host = (string) preg_replace('/^www\./', '', $host);

if ($host === '') {
    fwrite(STDERR, "Error: host argument is empty after normalization.\n");
    exit(1);
}

/* ---------------- Generate key ---------------- */

$config = MODULES[$module];

if ($module === 'bundle') {
    $payload = BUNDLE_ID . ':' . $host;
    $key     = hash_hmac('sha256', $payload, BUNDLE_SECRET);
    $configPath = $config['config'];
    $instructions = "Paste into Stores > Configuration > ETechFlow > Abandoned Cart Email > License > Bundle License Key.";
} else {
    $secret  = implode('', $config['fragments']);
    $payload = $host . ':' . $config['id'];
    $key     = hash_hmac('sha256', $payload, $secret);
    $configPath = $config['config'];
    $instructions = "Paste into Stores > Configuration > ETechFlow > Abandoned Cart Email > License > License Key.";
}

/* ---------------- Output ---------------- */

echo "============================================================\n";
echo " ETechFlow License Generator\n";
echo "------------------------------------------------------------\n";
echo "  Module:      " . $config['label'] . "\n";
echo "  Module ID:   " . $config['id'] . "\n";
echo "  Host:        $host\n";
echo "  Config path: $configPath\n";
echo "------------------------------------------------------------\n";
echo "  License key:\n";
echo "  $key\n";
echo "============================================================\n";
echo $instructions . "\n";
echo "Production Environment must be set to Yes.\n";

function printHelp(): void
{
    fwrite(STDERR, "Usage: php tools/generate-license.php --domain=<host> [--module=<slug>]\n\n");
    fwrite(STDERR, "Options:\n");
    fwrite(STDERR, "  --domain=<host>     Storefront domain (required)\n");
    fwrite(STDERR, "  --module=<slug>     One of: abandoned-cart-popup (default), bundle\n");
    fwrite(STDERR, "\nExamples:\n");
    fwrite(STDERR, "  php tools/generate-license.php --domain=shop.example.com\n");
    fwrite(STDERR, "  php tools/generate-license.php --domain=shop.example.com --module=bundle\n");
}
