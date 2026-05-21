<?php
/**
 * Etechflow_AbandonedCart - License validator.
 *
 * HMAC-signed per-installation license with dev-host detection and bundle
 * support. Compiles the BUNDLE_SECRET into the class (treat as a code-signing
 * private key — repo must stay private). The same secret is used by
 * tools/generate-license.php to mint keys.
 *
 * Per ETechFlow Module Development Standards §4:
 *   - HMAC scheme so validation is offline (no phoning home, no rate-limited
 *     external service to break customers' storefronts).
 *   - Dev-host detection auto-passes on .test, .local, .docksal, .ddev,
 *     .lando, localhost, 127.0.0.1, etc., so engineers don't need a real
 *     key locally.
 *   - Bundle support: BUNDLE_SECRET + BUNDLE_ID are identical across all
 *     ETechFlow modules in the same bundle, so one key activates them all.
 *     ALWAYS grep-search for BUNDLE_SECRET before touching this constant —
 *     drift between modules = silently-broken bundle keys.
 *   - Failure mode: silent no-op (admin sees a banner; storefront never
 *     breaks). The caller's `isEnabled()` check returns false and the
 *     observer/plugin returns without acting.
 *
 * License key format: "{lowercased-host}|{hex-hmac-sha256}"
 * Verification: split on `|`, confirm host matches request host (case-fold,
 * www. stripped), recompute HMAC, hash_equals.
 *
 * Result is cached per request to avoid recomputing HMAC on every hot-path
 * fire. Cache reset only happens on PHP process boundary, which is fine —
 * config changes require cache:flush anyway.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

final class LicenseValidator
{
    /**
     * Shared across every ETechFlow Magento module in this bundle. If you add
     * a license validator to another module (HelloShop, Core, future modules),
     * COPY THIS VALUE EXACTLY. Drift here breaks bundle keys.
     */
    private const BUNDLE_SECRET = '***REDACTED-BUNDLE-SECRET***';

    private const BUNDLE_ID = 'ETECHFLOW_MAGENTO_BUNDLE_V1';

    /**
     * Hosts (and host suffixes) that auto-pass license validation. Lets
     * engineers run the module locally on Warden/DDEV/Docksal/Lando/Docker
     * without minting a key first. Matched as either exact equality OR
     * "host ends with .{pattern}".
     */
    private const DEV_HOST_PATTERNS = [
        '.test',
        '.local',
        '.localhost',
        '.warden.test',
        '.docksal',
        '.ddev',
        '.lando',
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
    ];

    private ?bool $cachedResult = null;

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isValid(): bool
    {
        if ($this->cachedResult !== null) {
            return $this->cachedResult;
        }

        try {
            if (!$this->config->isProductionEnvironment()) {
                return $this->cachedResult = true;
            }

            $host = $this->extractHost();

            if ($host === '' || $this->isDevHost($host)) {
                return $this->cachedResult = true;
            }

            $key = trim($this->config->getLicenseKey());
            if ($key === '') {
                $this->logger->warning(
                    'Etechflow_AbandonedCart: license key missing, module silently disabled',
                    ['host' => $host]
                );
                return $this->cachedResult = false;
            }

            return $this->cachedResult = $this->verifyKey($key, $host);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: license validator threw — failing closed',
                ['exception' => $e->getMessage()]
            );
            return $this->cachedResult = false;
        }
    }

    private function extractHost(): string
    {
        $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        $host = strtolower($host);
        $host = (string) preg_replace('/:\d+$/', '', $host);
        $host = (string) preg_replace('/^www\./', '', $host);
        return $host;
    }

    private function isDevHost(string $host): bool
    {
        foreach (self::DEV_HOST_PATTERNS as $pattern) {
            if ($host === $pattern) {
                return true;
            }
            if (str_starts_with($pattern, '.') && str_ends_with($host, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function verifyKey(string $key, string $host): bool
    {
        $parts = explode('|', $key, 2);
        if (count($parts) !== 2) {
            $this->logger->warning('Etechflow_AbandonedCart: license key malformed (expected host|hmac)');
            return false;
        }

        [$keyHost, $providedHmac] = $parts;
        $keyHost = strtolower($keyHost);
        $keyHost = (string) preg_replace('/^www\./', '', $keyHost);

        if ($keyHost !== $host) {
            $this->logger->warning(
                'Etechflow_AbandonedCart: license host mismatch',
                ['license_host' => $keyHost, 'request_host' => $host]
            );
            return false;
        }

        $expectedHmac = $this->computeHmac($host);
        if (!hash_equals($expectedHmac, $providedHmac)) {
            $this->logger->warning(
                'Etechflow_AbandonedCart: license HMAC mismatch',
                ['host' => $host]
            );
            return false;
        }

        return true;
    }

    private function computeHmac(string $host): string
    {
        return hash_hmac('sha256', self::BUNDLE_ID . ':' . $host, self::BUNDLE_SECRET);
    }
}
