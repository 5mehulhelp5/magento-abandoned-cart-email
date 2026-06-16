<?php
/**
 * Etechflow_AbandonedCart - License validator (v1.3.0).
 *
 * Layered validation per LICENSING_PROTOCOL.md + PORTAL_LICENSING_GUIDE.md:
 *
 *   1. Dev hosts (.test, .local, localhost, ngrok, etc.) auto-bypass.
 *   2. Production Environment = No bypasses the check entirely.
 *   3. SP-XXXX keys validate via the portal (domain + server IP + active
 *      subscription). Cached 30s for valid / 60s for invalid.
 *   4. HMAC per-module key (SECRET_FRAGMENTS) — offline self-validating.
 *   5. HMAC bundle key (BUNDLE_SECRET) — one key activates all modules.
 *
 * On IP-block events the portal returns `ip_blocked:true`; this class
 * clears the license_key and raises the ip_blocked flag. When the IP
 * is restored, the next portal check sees `valid:true` again and the
 * key is restored from `issued_key`. A manual clear (ip_blocked = 0)
 * stays locked — never auto-restores.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

final class LicenseValidator
{
    /* ---------------- Per-module identity (LICENSING_PROTOCOL §3) ---------- */

    public const MODULE_ID = 'abandoned-cart';

    /**
     * Four random strings unique to THIS module. Combined to form the per-module
     * HMAC secret. Drift between PHP, CLI generator, and webstore = silently
     * broken keys. Keep IDENTICAL across `tools/generate-license.php` +
     * `docs/license.ts.example`.
     */
    private const SECRET_FRAGMENTS = [
        'Rtqm8EdwsuD3',
        'ftqjg2Geppx0',
        'pMYds482oUJt',
        '1TitqYJr7Cuc',
    ];

    /* ---------------- Shared bundle (must match other ETechFlow modules) --- */

    public const BUNDLE_ID = 'etechflow-bundle';

    /**
     * Shared bundle secret fragments. Concatenated at runtime — MUST match
     * tools/generate-license.php and every sibling ETechFlow module exactly.
     */
    private const BUNDLE_SECRET_FRAGMENTS = [
        'eTF-BUNDLE-2026',
        'k2D9-mP4x',
        'L8nR-vH2j',
        'X7tY-zW5q',
    ];

    /* ---------------- Admin config paths ----------------------------------- */

    public const XML_PATH_LICENSE_KEY     = 'etechflow_abandoned_cart/license/key';
    public const XML_PATH_PRODUCTION      = 'etechflow_abandoned_cart/license/is_production';
    public const XML_PATH_ISSUED_KEY      = 'etechflow_abandoned_cart/license/issued_key';
    public const XML_PATH_ISSUED_AT       = 'etechflow_abandoned_cart/license/issued_at';
    public const XML_PATH_IP_BLOCKED      = 'etechflow_abandoned_cart/license/ip_blocked';
    public const XML_PATH_PORTAL_URL      = 'etechflow_abandoned_cart/license/portal_url';
    public const XML_PATH_BUNDLE_LICENSE_KEY = 'etechflow_bundle/license/license_key';

    /* ---------------- Portal + cache + grace ------------------------------- */

    private const DEFAULT_PORTAL_URL  = 'https://license.etechflow.com/license/validate';
    public  const PORTAL_CACHE_TTL     = 30;
    public  const PORTAL_CACHE_TTL_BAD = 60;
    private const CACHE_TAG    = 'ETECHFLOW_ABC';
    private const CACHE_PREFIX = 'etf_abc_lic_';
    /** 48-hour grace if portal unreachable and we have an issued_key. */
    private const GRACE_SECONDS = 172800;

    /* ---------------- Dev host bypass list --------------------------------- */

    private const DEV_HOST_PATTERNS = [
        '.test',
        '.local',
        '.localhost',
        '.warden.test',
        '.docksal',
        '.ddev',
        '.lando',
        '.ngrok.io',
        '.ngrok-free.app',
        '.ngrok-free.dev',
        '.loca.lt',
        '.magento.cloud',
        '.magentocloud.com',
        '.example',
        '.invalid',
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
    ];

    private ?bool $cachedResult = null;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly CacheInterface $cache,
        private readonly Curl $curl,
        private readonly WriterInterface $configWriter,
        private readonly LoggerInterface $logger,
    ) {
    }

    /* ====================================================================== */
    /* Public API                                                             */
    /* ====================================================================== */

    public function isValid(): bool
    {
        if ($this->cachedResult !== null) {
            return $this->cachedResult;
        }

        try {
            // Production toggle off → bypass for dev/staging configs
            if (!$this->isProductionEnvironment()) {
                return $this->cachedResult = true;
            }

            $host = $this->getCurrentHost();

            if ($host === '' || $this->isDevelopmentHost($host)) {
                return $this->cachedResult = true;
            }

            return $this->cachedResult = $this->checkKey($host);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: license validator threw — failing closed',
                ['exception' => $e->getMessage()]
            );
            return $this->cachedResult = false;
        }
    }

    /**
     * Save license_key + clear the ip_blocked flag. Called after Stripe
     * checkout completes successfully.
     */
    public function writeLicenseKey(string $key): void
    {
        $this->configWriter->save(self::XML_PATH_LICENSE_KEY, $key);
        $this->configWriter->save(self::XML_PATH_IP_BLOCKED, '0');
        $this->cache->clean([self::CACHE_TAG]);
        $this->cachedResult = null;
    }

    /**
     * Clear license_key + set ip_blocked=1 (so isValid can later auto-restore
     * via the issued_key when the IP unblocks).
     */
    public function clearLicenseKey(): void
    {
        $current = (string) $this->scopeConfig->getValue(self::XML_PATH_LICENSE_KEY, ScopeInterface::SCOPE_STORE);
        if ($current !== '') {
            $this->configWriter->save(self::XML_PATH_LICENSE_KEY, '');
            $this->configWriter->save(self::XML_PATH_IP_BLOCKED, '1');
            $this->cache->clean([self::CACHE_TAG]);
            $this->cachedResult = null;
        }
    }

    public function getPortalUrl(): string
    {
        $configured = trim((string) $this->scopeConfig->getValue(self::XML_PATH_PORTAL_URL, ScopeInterface::SCOPE_STORE));
        return $configured !== '' ? $configured : self::DEFAULT_PORTAL_URL;
    }

    public function getCurrentHost(): string
    {
        $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        return $this->canonicalize($host);
    }

    public function computeKey(string $host): string
    {
        $secret = implode('', self::SECRET_FRAGMENTS);
        $payload = $this->canonicalize($host) . ':' . self::MODULE_ID;
        return hash_hmac('sha256', $payload, $secret);
    }

    public function computeBundleKey(string $host): string
    {
        $payload = self::BUNDLE_ID . ':' . $this->canonicalize($host);
        return hash_hmac('sha256', $payload, implode('', self::BUNDLE_SECRET_FRAGMENTS));
    }

    /* ====================================================================== */
    /* Internal — key validation                                              */
    /* ====================================================================== */

    private function checkKey(string $host): bool
    {
        $configured = trim((string) $this->scopeConfig->getValue(self::XML_PATH_LICENSE_KEY, ScopeInterface::SCOPE_STORE));
        $isEmpty    = ($configured === '');

        // Empty key — fall back to issued_key ONLY if a prior IP-block cleared it
        if ($isEmpty) {
            if ((int) $this->scopeConfig->getValue(self::XML_PATH_IP_BLOCKED) !== 1) {
                return false;
            }
            $configured = trim((string) $this->scopeConfig->getValue(self::XML_PATH_ISSUED_KEY, ScopeInterface::SCOPE_STORE));
            if ($configured === '') {
                return false;
            }
        }

        // SP-XXXX keys → portal validation
        if (str_starts_with($configured, 'SP-')) {
            // 48h grace period if locally issued recently (lets storefronts survive brief portal outages)
            if (!$isEmpty && $this->isLocallyIssuedKey($configured)) {
                return true;
            }

            $valid = $this->validateViaPortal($host, $configured);

            // First valid portal response + IP-block had cleared the key → restore it
            if ($valid && $isEmpty) {
                $this->writeLicenseKey($configured);
            }

            return $valid;
        }

        // HMAC per-module key
        if (hash_equals($this->computeKey($host), $configured)) {
            return true;
        }

        // HMAC bundle key
        $bundleKey = trim((string) $this->scopeConfig->getValue(self::XML_PATH_BUNDLE_LICENSE_KEY, ScopeInterface::SCOPE_STORE));
        if ($bundleKey === '') {
            return false;
        }
        return hash_equals($this->computeBundleKey($host), $bundleKey);
    }


    /* ====================================================================== */
    /* Portal validation                                                      */
    /* ====================================================================== */

    private function validateViaPortal(string $host, string $key): bool
    {
        $cacheKey = self::CACHE_PREFIX . md5($host . ':' . $key);
        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            return $cached === '1';
        }

        $url = $this->getPortalUrl()
            . '?domain=' . urlencode($host)
            . '&license_key=' . urlencode($key)
            . '&platform=magento'
            . '&module=' . urlencode(self::MODULE_ID);

        $ipBlocked = false;
        $valid = false;

        try {
            $this->curl->setOption(CURLOPT_TIMEOUT, 10);
            $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
            $this->curl->addHeader('Accept', 'application/json');
            $this->curl->get($url);

            $status = (int) $this->curl->getStatus();
            $body   = (string) $this->curl->getBody();
            $data   = json_decode($body, true);

            if ($status === 200 && is_array($data) && !empty($data['valid'])) {
                $valid = true;
            }
            if ($status === 403 && is_array($data) && !empty($data['ip_blocked'])) {
                $ipBlocked = true;
            }
        } catch (\Throwable $e) {
            // Portal unreachable — DO NOT cache deny (don't punish customers for our outages)
            $this->logger->warning(
                'Etechflow_AbandonedCart: portal validation network error',
                ['exception' => $e->getMessage(), 'host' => $host]
            );
            return false;
        }

        $ttl = $valid ? self::PORTAL_CACHE_TTL : self::PORTAL_CACHE_TTL_BAD;
        $this->cache->save($valid ? '1' : '0', $cacheKey, [self::CACHE_TAG], $ttl);

        // First valid response — record issued_key + issued_at for IP-block restore + grace
        if ($valid) {
            $issuedKey = trim((string) $this->scopeConfig->getValue(self::XML_PATH_ISSUED_KEY, ScopeInterface::SCOPE_STORE));
            if ($issuedKey === '') {
                $this->configWriter->save(self::XML_PATH_ISSUED_KEY, $key);
                $this->configWriter->save(self::XML_PATH_ISSUED_AT, (string) time());
                $this->cache->clean([self::CACHE_TAG]);
            }
        }

        // IP block → clear current key (kept in issued_key for auto-restore later)
        if ($ipBlocked) {
            $this->clearLicenseKey();
        }

        return $valid;
    }

    /**
     * True if the configured key matches a recently-issued one (within 48h).
     * Lets us survive short portal outages without locking out a paid customer.
     */
    private function isLocallyIssuedKey(string $key): bool
    {
        $issuedKey = trim((string) $this->scopeConfig->getValue(self::XML_PATH_ISSUED_KEY, ScopeInterface::SCOPE_STORE));
        $issuedAt  = (int) $this->scopeConfig->getValue(self::XML_PATH_ISSUED_AT, ScopeInterface::SCOPE_STORE);
        if ($issuedAt === 0 || time() - $issuedAt > self::GRACE_SECONDS) {
            return false;
        }
        return hash_equals($issuedKey, $key);
    }

    /* ====================================================================== */
    /* Helpers                                                                */
    /* ====================================================================== */

    private function isProductionEnvironment(): bool
    {
        // Production-environment toggle removed: licensing is always enforced.
        return true;
    }

    private function canonicalize(string $host): string
    {
        $host = strtolower(trim($host));
        $host = (string) preg_replace('/:\d+$/', '', $host);
        $host = (string) preg_replace('/^www\./', '', $host);
        return $host;
    }

    private function isDevelopmentHost(string $host): bool
    {
        foreach (self::DEV_HOST_PATTERNS as $pattern) {
            if ($host === $pattern) {
                return true;
            }
            if (str_starts_with($pattern, '.') && str_ends_with($host, $pattern)) {
                return true;
            }
        }
        // RFC 1918 private IP ranges (10.x, 172.16-31.x, 192.168.x)
        if (preg_match('/^10\./', $host)) {
            return true;
        }
        if (preg_match('/^192\.168\./', $host)) {
            return true;
        }
        if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $host)) {
            return true;
        }
        if (preg_match('/^127\.\d+\.\d+\.\d+$/', $host)) {
            return true;
        }
        return false;
    }
}
