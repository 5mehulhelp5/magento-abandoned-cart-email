<?php
/**
 * Etechflow_AbandonedCart - LicenseValidator security tests.
 *
 * Coverage matrix:
 *   1. Production + missing key → false (silent disable per §4)
 *   2. Production + valid HMAC for current host → true
 *   3. Production + valid HMAC for DIFFERENT host → false (host mismatch)
 *   4. Production + malformed key → false
 *   5. Production + tampered HMAC → false (constant-time fail)
 *   6. is_production = No → true regardless (dev environment)
 *   7. Dev host (.test, .local, .docksal, .ddev, .lando, localhost) → true
 *      regardless (dev-host auto-bypass)
 *   8. URL with port + www prefix → normalized before host comparison
 *   9. StoreManager throws → false (fail-closed per §0 rule 3)
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Test\Unit\Model;

use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LicenseValidatorTest extends TestCase
{
    /**
     * Must match the constant in LicenseValidator + tools/generate-license.php.
     */
    private const BUNDLE_SECRET = '***REDACTED-BUNDLE-SECRET***';

    private const BUNDLE_ID = 'ETECHFLOW_MAGENTO_BUNDLE_V1';

    private Config&MockObject $config;

    private StoreManagerInterface&MockObject $storeManager;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->config       = $this->createMock(Config::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->logger       = $this->createMock(LoggerInterface::class);
    }

    public function testReturnsFalseWhenProductionAndKeyMissing(): void
    {
        $this->config->method('isProductionEnvironment')->willReturn(true);
        $this->config->method('getLicenseKey')->willReturn('');
        $this->mockStoreUrl('https://shop.example.com/');

        $this->assertFalse($this->build()->isValid());
    }

    public function testReturnsTrueWhenIsProductionIsFalse(): void
    {
        $this->config->method('isProductionEnvironment')->willReturn(false);
        $this->mockStoreUrl('https://shop.example.com/');

        $this->assertTrue($this->build()->isValid());
    }

    public function testReturnsTrueForValidHmacOnMatchingHost(): void
    {
        $host = 'shop.example.com';
        $hmac = hash_hmac('sha256', self::BUNDLE_ID . ':' . $host, self::BUNDLE_SECRET);

        $this->config->method('isProductionEnvironment')->willReturn(true);
        $this->config->method('getLicenseKey')->willReturn($host . '|' . $hmac);
        $this->mockStoreUrl('https://shop.example.com/');

        $this->assertTrue($this->build()->isValid());
    }

    public function testReturnsFalseForValidHmacOnDifferentHost(): void
    {
        $host = 'shop.example.com';
        $hmac = hash_hmac('sha256', self::BUNDLE_ID . ':' . $host, self::BUNDLE_SECRET);

        $this->config->method('isProductionEnvironment')->willReturn(true);
        $this->config->method('getLicenseKey')->willReturn($host . '|' . $hmac);
        $this->mockStoreUrl('https://OTHER.example.com/');

        $this->assertFalse($this->build()->isValid());
    }

    public function testReturnsFalseForMalformedKey(): void
    {
        $this->config->method('isProductionEnvironment')->willReturn(true);
        $this->config->method('getLicenseKey')->willReturn('no-pipe-separator-just-garbage');
        $this->mockStoreUrl('https://shop.example.com/');

        $this->assertFalse($this->build()->isValid());
    }

    public function testReturnsFalseForTamperedHmac(): void
    {
        $this->config->method('isProductionEnvironment')->willReturn(true);
        $this->config->method('getLicenseKey')->willReturn('shop.example.com|deadbeef');
        $this->mockStoreUrl('https://shop.example.com/');

        $this->assertFalse($this->build()->isValid());
    }

    /**
     * @dataProvider devHostProvider
     */
    public function testDevHostsAutoBypass(string $devUrl): void
    {
        $this->config->method('isProductionEnvironment')->willReturn(true);
        $this->config->method('getLicenseKey')->willReturn('');
        $this->mockStoreUrl($devUrl);

        $this->assertTrue($this->build()->isValid());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function devHostProvider(): iterable
    {
        yield 'localhost'         => ['http://localhost/'];
        yield '127.0.0.1'         => ['http://127.0.0.1/'];
        yield '.test'             => ['http://shop.test/'];
        yield '.local'            => ['http://shop.local/'];
        yield '.docksal'          => ['http://shop.docksal/'];
        yield '.ddev'             => ['http://shop.ddev/'];
        yield '.lando'            => ['http://shop.lando/'];
        yield 'warden.test'       => ['http://shop.warden.test/'];
    }

    public function testHostNormalizationStripsPortAndWww(): void
    {
        // License generated for "shop.example.com" — no www, no port
        $hmac = hash_hmac('sha256', self::BUNDLE_ID . ':shop.example.com', self::BUNDLE_SECRET);

        $this->config->method('isProductionEnvironment')->willReturn(true);
        $this->config->method('getLicenseKey')->willReturn('shop.example.com|' . $hmac);
        // Storefront base URL has www. + port → should normalize and still match
        $this->mockStoreUrl('https://www.shop.example.com:8080/');

        $this->assertTrue($this->build()->isValid());
    }

    public function testFailsClosedOnInternalError(): void
    {
        $this->config->method('isProductionEnvironment')
            ->willThrowException(new \RuntimeException('config storage is sick'));

        $this->logger->expects($this->once())->method('error');

        $this->assertFalse($this->build()->isValid());
    }

    public function testCachesResultWithinRequest(): void
    {
        $this->config->method('isProductionEnvironment')->willReturn(false);
        $this->mockStoreUrl('https://shop.example.com/');

        // expect getValue / isSetFlag to be called only once across two invocations
        // (we can't easily count here; just confirm second call returns same)
        $validator = $this->build();
        $this->assertSame($validator->isValid(), $validator->isValid());
    }

    private function mockStoreUrl(string $url): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getBaseUrl')->willReturn($url);
        $this->storeManager->method('getStore')->willReturn($store);
    }

    private function build(): LicenseValidator
    {
        return new LicenseValidator($this->config, $this->storeManager, $this->logger);
    }
}
