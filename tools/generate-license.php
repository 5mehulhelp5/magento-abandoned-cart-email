<?php
/**
 * Etechflow_AbandonedCart - License key minting tool.
 *
 * CLI helper that produces a license key for a given storefront host.
 * Output is paste-ready for the admin config field `License Key` at
 * Stores → Configuration → ETechFlow → Abandoned Cart Email → License.
 *
 * **BUNDLE_SECRET + BUNDLE_ID MUST exactly match the constants in
 * [[Etechflow\AbandonedCart\Model\LicenseValidator]].** If you change one,
 * change the other. Grep for `BUNDLE_SECRET` in the repo before editing.
 *
 * Usage:
 *   php tools/generate-license.php shop.example.com
 *   php tools/generate-license.php magento-dev.etechflow.com
 *
 * Notes:
 *   - Hosts are case-folded and stripped of `www.` and `:port` before being
 *     baked into the key, matching the validator's normalization.
 *   - This script is INTERNAL — do not ship it inside the customer-facing
 *     module zip. Phase 22's release build excludes `tools/`.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

const BUNDLE_SECRET = '***REDACTED-BUNDLE-SECRET***';
const BUNDLE_ID     = 'ETECHFLOW_MAGENTO_BUNDLE_V1';

if ($argc < 2 || in_array($argv[1], ['-h', '--help'], true)) {
    fwrite(STDERR, "Usage: php tools/generate-license.php <host>\n");
    fwrite(STDERR, "Example: php tools/generate-license.php shop.example.com\n");
    exit(1);
}

$host = strtolower(trim($argv[1]));
$host = (string) preg_replace('/:\d+$/', '', $host);
$host = (string) preg_replace('/^www\./', '', $host);
$host = (string) preg_replace('/^https?:\/\//', '', $host);

if ($host === '') {
    fwrite(STDERR, "Error: host argument is empty after normalization.\n");
    exit(1);
}

$hmac = hash_hmac('sha256', BUNDLE_ID . ':' . $host, BUNDLE_SECRET);
$key  = $host . '|' . $hmac;

echo "============================================================\n";
echo " ETechFlow_AbandonedCart license key\n";
echo "------------------------------------------------------------\n";
echo "  Host:        $host\n";
echo "  Bundle:      " . BUNDLE_ID . "\n";
echo "  License key: $key\n";
echo "============================================================\n";
echo "Paste the license key into:\n";
echo "  Admin > Stores > Configuration > ETechFlow > Abandoned Cart Email > License > License Key\n";
echo "Confirm Production Environment = Yes (will be ignored otherwise).\n";
