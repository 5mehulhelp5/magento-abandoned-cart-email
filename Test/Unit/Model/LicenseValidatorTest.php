<?php
/**
 * Etechflow_AbandonedCart - LicenseValidator security tests (v1.3.0).
 *
 * Coverage:
 *   1. Production + missing key → false
 *   2. Production + valid per-module HMAC key → true
 *   3. Production + valid bundle key → true
 *   4. Production + tampered key → false
 *   5. is_production = No → true (dev bypass)
 *   6. Dev hosts (.test, .local, localhost, ngrok-free.dev) → true
 *   7. Legacy v1.0 "host|hmac" format still accepted (backward-compat)
 *   8. SP-XXXX key without portal reachable → false (fails closed)
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Test\Unit\Model;

use Etechflow\AbandonedCart\Model\LicenseValidator;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LicenseValidatorTest extends TestCase
{
    private const BUNDLE_SECRET = '***REDACTED-BUNDLE-SECRET***';
    private const BUNDLE_ID     = 'ETECHFLOW_MAGENTO_BUNDLE_V1';
    private const MODULE_ID     = 'abandoned-cart-popup';

    private const SECRET_FRAGMENTS = [
        '***REDACTED-FRAGMENT-1***',
        '***REDACTED-FRAGMENT-2***',
        '***REDACTED-FRAGMENT-3***',
        '***REDACTED-FRAGMENT-4***',
    ];

    private ScopeConfigInterface&MockObject $scopeConfig;
    private StoreManagerInterface&MockObject $storeManager;
    private CacheInterface&MockObject $cache;
    private Curl&MockObject $curl;
    private WriterInterface&MockObject $configWriter;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->scopeConfig  = $this->createMock(ScopeConfigInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->cache        = $this->createMock(CacheInterface::class);
        $this->curl         = $this->createMock(Curl::class);
        $this->configWriter = $this->createMock(WriterInterface::class);
        $this->logger       = $this->createMock(LoggerInterface::class);

        $this->cache->method('load')->willReturn(false);
    }

    private function newValidator(): LicenseValidator
    {
        return new LicenseValidator(
            $this->scopeConfig,
            $this->storeManager,
            $this->cache,
            $this->curl,
            $this->configWriter,
            $this->logger,
        );
    }

    private function stubHost(string $host): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getBaseUrl')->willReturn('https://' . $host . '/');
        $this->storeManager->method('getStore')->willReturn($store);
    }

    private function stubConfig(array $values): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path) use ($values) {
                return $values[$path] ?? null;
            }
        );
    }

    /* ---------------- Tests ---------------- */

    public function testProductionWithMissingKeyFailsClosed(): void
    {
        $this->stubHost('shop.example.com');
        $this->stubConfig([
            LicenseValidator::XML_PATH_PRODUCTION => '1',
            LicenseValidator::XML_PATH_LICENSE_KEY => '',
        ]);

        self::assertFalse($this->newValidator()->isValid());
    }

    public function testValidPerModuleHmacKeyPasses(): void
    {
        $host = 'shop.example.com';
        $secret = implode('', self::SECRET_FRAGMENTS);
        $expectedKey = hash_hmac('sha256', $host . ':' . self::MODULE_ID, $secret);

        $this->stubHost($host);
        $this->stubConfig([
            LicenseValidator::XML_PATH_PRODUCTION => '1',
            LicenseValidator::XML_PATH_LICENSE_KEY => $expectedKey,
        ]);

        self::assertTrue($this->newValidator()->isValid());
    }

    public function testValidBundleHmacKeyPasses(): void
    {
        $host = 'shop.example.com';
        $bundleKey = hash_hmac('sha256', self::BUNDLE_ID . ':' . $host, self::BUNDLE_SECRET);

        $this->stubHost($host);
        $this->stubConfig([
            LicenseValidator::XML_PATH_PRODUCTION => '1',
            LicenseValidator::XML_PATH_LICENSE_KEY => 'wrong-per-module-key',
            LicenseValidator::XML_PATH_BUNDLE_LICENSE_KEY => $bundleKey,
        ]);

        self::assertTrue($this->newValidator()->isValid());
    }

    public function testTamperedKeyFailsClosed(): void
    {
        $this->stubHost('shop.example.com');
        $this->stubConfig([
            LicenseValidator::XML_PATH_PRODUCTION => '1',
            LicenseValidator::XML_PATH_LICENSE_KEY => 'tampered-garbage',
        ]);

        self::assertFalse($this->newValidator()->isValid());
    }

    public function testProductionDisabledBypassesChecks(): void
    {
        $this->stubHost('shop.example.com');
        $this->stubConfig([
            LicenseValidator::XML_PATH_PRODUCTION => '0',
            LicenseValidator::XML_PATH_LICENSE_KEY => '',
        ]);

        self::assertTrue($this->newValidator()->isValid());
    }

    /**
     * @dataProvider devHostProvider
     */
    public function testDevHostsAutoBypass(string $host): void
    {
        $this->stubHost($host);
        $this->stubConfig([
            LicenseValidator::XML_PATH_PRODUCTION => '1',
            LicenseValidator::XML_PATH_LICENSE_KEY => '',
        ]);

        self::assertTrue($this->newValidator()->isValid(), "Failed for host: $host");
    }

    public static function devHostProvider(): array
    {
        return [
            ['localhost'],
            ['shop.test'],
            ['shop.local'],
            ['127.0.0.1'],
            ['192.168.1.10'],
            ['10.0.0.5'],
            ['shop.ngrok-free.dev'],
            ['shop.magento.cloud'],
        ];
    }

    public function testLegacyFormatAccepted(): void
    {
        $host = 'shop.example.com';
        $legacyHmac = hash_hmac('sha256', self::BUNDLE_ID . ':' . $host, self::BUNDLE_SECRET);
        $legacyKey  = $host . '|' . $legacyHmac;

        $this->stubHost($host);
        $this->stubConfig([
            LicenseValidator::XML_PATH_PRODUCTION => '1',
            LicenseValidator::XML_PATH_LICENSE_KEY => $legacyKey,
        ]);

        self::assertTrue($this->newValidator()->isValid());
    }

    public function testSpKeyWithUnreachablePortalFailsClosed(): void
    {
        $this->stubHost('shop.example.com');
        $this->stubConfig([
            LicenseValidator::XML_PATH_PRODUCTION => '1',
            LicenseValidator::XML_PATH_LICENSE_KEY => 'SP-ABC123XYZ',
        ]);

        // Simulate curl throwing (portal unreachable)
        $this->curl->method('get')->willThrowException(new \RuntimeException('Connection refused'));

        self::assertFalse($this->newValidator()->isValid());
    }
}
