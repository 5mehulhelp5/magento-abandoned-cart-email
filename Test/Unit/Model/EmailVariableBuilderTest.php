<?php
/**
 * Etechflow_AbandonedCart - EmailVariableBuilder tests.
 *
 * Locks down the URL contract between Phase 12 (email rendering) and
 * Phase 13 (frontend controllers). If route paths change in either phase,
 * this test catches the drift.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Test\Unit\Model;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\EmailVariableBuilder;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EmailVariableBuilderTest extends TestCase
{
    private Config&MockObject $config;

    private StoreManagerInterface&MockObject $storeManager;

    private EmailVariableBuilder $builder;

    protected function setUp(): void
    {
        $this->config       = $this->createMock(Config::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getBaseUrl')->willReturn('https://shop.example.com/');
        $this->storeManager->method('getStore')->willReturn($store);

        $this->config->method('getUtmSource')->willReturn('etechflow_abandoned_cart');
        $this->config->method('getUtmMedium')->willReturn('email');
        $this->config->method('getUtmCampaign')->willReturn('cart_recovery');

        $this->builder = new EmailVariableBuilder($this->config, $this->storeManager);
    }

    public function testRestoreUrlIncludesTokenAndUtmParams(): void
    {
        $cart = $this->mockCart('test@example.com', 'token-abc-123');
        $log  = $this->mockLog(42);

        $vars = $this->builder->build($cart, $log);

        $url = $vars['restore_url'];
        $this->assertStringContainsString('etechflow_abandonedcart/restore/index/', $url);
        $this->assertStringContainsString('t=token-abc-123', $url);
        $this->assertStringContainsString('utm_source=etechflow_abandoned_cart', $url);
        $this->assertStringContainsString('utm_medium=email', $url);
        $this->assertStringContainsString('utm_campaign=cart_recovery', $url);
    }

    public function testUnsubscribeUrlUsesSameToken(): void
    {
        $cart = $this->mockCart('test@example.com', 'token-abc-123');
        $log  = $this->mockLog(42);

        $vars = $this->builder->build($cart, $log);

        $this->assertStringContainsString('etechflow_abandonedcart/unsubscribe/index/', $vars['unsubscribe_url']);
        $this->assertStringContainsString('t=token-abc-123', $vars['unsubscribe_url']);
    }

    public function testTrackingPixelUrlPresentWhenTrackingEnabled(): void
    {
        $this->config->method('isOpenTrackingEnabled')->willReturn(true);
        $cart = $this->mockCart('test@example.com', 'token-abc');
        $log  = $this->mockLog(99);

        $vars = $this->builder->build($cart, $log);

        $this->assertNotNull($vars['tracking_pixel_url']);
        $this->assertStringContainsString('etechflow_abandonedcart/track/open/', $vars['tracking_pixel_url']);
        $this->assertStringContainsString('l=99', $vars['tracking_pixel_url']);
    }

    public function testTrackingPixelUrlNullWhenTrackingDisabled(): void
    {
        $this->config->method('isOpenTrackingEnabled')->willReturn(false);
        $cart = $this->mockCart('test@example.com', 'token-abc');
        $log  = $this->mockLog(99);

        $vars = $this->builder->build($cart, $log);

        $this->assertNull($vars['tracking_pixel_url']);
    }

    public function testCustomerFirstnameDefaultsToEmptyString(): void
    {
        $cart = $this->mockCart('test@example.com', 'tok', firstname: null);
        $log  = $this->mockLog(1);

        $vars = $this->builder->build($cart, $log);
        $this->assertSame('', $vars['customer_firstname']);
    }

    public function testCouponFieldsArePassedThrough(): void
    {
        $cart = $this->mockCart('test@example.com', 'tok');
        $log  = $this->mockLog(1);

        $vars = $this->builder->build($cart, $log, 'ETF-DEADBEEF', '10% off');

        $this->assertSame('ETF-DEADBEEF', $vars['coupon_code']);
        $this->assertSame('10% off', $vars['coupon_label']);
    }

    private function mockCart(string $email, string $token, ?string $firstname = 'Jane'): AbandonedCartInterface
    {
        $cart = $this->createMock(AbandonedCartInterface::class);
        $cart->method('getStoreId')->willReturn(1);
        $cart->method('getCustomerEmail')->willReturn($email);
        $cart->method('getCustomerFirstname')->willReturn($firstname);
        $cart->method('getRestoreToken')->willReturn($token);
        return $cart;
    }

    private function mockLog(?int $logId): EmailLogInterface
    {
        $log = $this->createMock(EmailLogInterface::class);
        $log->method('getLogId')->willReturn($logId);
        return $log;
    }
}
