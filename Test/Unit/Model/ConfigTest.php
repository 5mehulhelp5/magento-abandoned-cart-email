<?php
/**
 * Etechflow_AbandonedCart - Config wrapper tests.
 *
 * Verifies every typed getter casts ScopeConfigInterface output to the
 * expected PHP type. Mocks Magento's ScopeConfigInterface — the wrapper
 * itself is pure delegation + casting, so this exercises the cast layer.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Test\Unit\Model;

use Etechflow\AbandonedCart\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function testIsEnabledDelegatesToIsSetFlag(): void
    {
        $this->scopeConfig
            ->expects($this->once())
            ->method('isSetFlag')
            ->with(
                Config::XML_PATH_GENERAL_ENABLED,
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled());
    }

    public function testIsEnabledPassesStoreId(): void
    {
        $this->scopeConfig
            ->expects($this->once())
            ->method('isSetFlag')
            ->with(
                Config::XML_PATH_GENERAL_ENABLED,
                ScopeInterface::SCOPE_STORE,
                42
            )
            ->willReturn(false);

        $this->assertFalse($this->config->isEnabled(42));
    }

    public function testGetAbandonmentThresholdMinutesCastsToInt(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('30');
        $this->assertSame(30, $this->config->getAbandonmentThresholdMinutes());
    }

    public function testGetSubtotalsAndCountsCastToInt(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('50');
        $this->assertSame(50, $this->config->getMaxEmailsPerCart());
        $this->assertSame(50, $this->config->getCronBatchSize());
        $this->assertSame(50, $this->config->getCronLockTimeoutMinutes());
        $this->assertSame(50, $this->config->getCronMaxRuntimeSeconds());
    }

    public function testGetSenderNameReturnsString(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('General Contact');
        $this->assertSame('General Contact', $this->config->getSenderName());
    }

    public function testGetReplyToEmailReturnsString(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('replies@example.com');
        $this->assertSame('replies@example.com', $this->config->getReplyToEmail());
    }

    public function testGetReplyToEmailReturnsEmptyStringWhenUnset(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->assertSame('', $this->config->getReplyToEmail());
    }

    public function testGetTokenExpiryDaysCastsToInt(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('30');
        $this->assertSame(30, $this->config->getRestoreTokenExpiryDays());
    }

    public function testIsAutoLoginCustomerOnRestoreDelegatesToFlag(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->willReturnMap([
                [Config::XML_PATH_RESTORE_AUTO_LOGIN, ScopeInterface::SCOPE_STORE, null, true],
            ]);
        $this->assertTrue($this->config->isAutoLoginCustomerOnRestore());
    }

    public function testTrackingGettersReturnExpectedTypes(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->willReturn(true);
        $this->scopeConfig
            ->method('getValue')
            ->willReturn('campaign');

        $this->assertTrue($this->config->isOpenTrackingEnabled());
        $this->assertTrue($this->config->isClickTrackingEnabled());
        $this->assertSame('campaign', $this->config->getUtmSource());
        $this->assertSame('campaign', $this->config->getUtmMedium());
        $this->assertSame('campaign', $this->config->getUtmCampaign());
    }

    public function testLicenseGetters(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                [Config::XML_PATH_LICENSE_KEY, ScopeInterface::SCOPE_STORE, null, 'abc|def'],
            ]);
        $this->scopeConfig
            ->method('isSetFlag')
            ->willReturnMap([
                [Config::XML_PATH_LICENSE_IS_PRODUCTION, ScopeInterface::SCOPE_STORE, null, true],
            ]);

        $this->assertSame('abc|def', $this->config->getLicenseKey());
        $this->assertTrue($this->config->isProductionEnvironment());
    }

    public function testSenderIdentityPassthroughPathConstruction(): void
    {
        $this->scopeConfig
            ->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnCallback(static function (string $path): string {
                return match ($path) {
                    'trans_email/ident_sales/email' => 'sales@example.com',
                    'trans_email/ident_sales/name'  => 'Sales Team',
                    default                          => '',
                };
            });

        $this->assertSame('sales@example.com', $this->config->getSenderEmailFromIdentity('sales'));
        $this->assertSame('Sales Team', $this->config->getSenderNameFromIdentity('sales'));
    }
}
