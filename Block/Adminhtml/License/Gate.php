<?php
/**
 * Etechflow_AbandonedCart - License gate page block.
 *
 * Exposes the 3 subscription plan prices/labels and the currency symbol
 * to the gate.phtml template. Reading directly from config in the phtml
 * is discouraged — this block does the work and the template only renders.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block\Adminhtml\License;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Gate extends Template
{
    private const CONFIG_PATH_PREFIX = 'etechflow_abandoned_cart/';

    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getWeeklyPrice(): float
    {
        return (float) $this->getConfigValue('plans/weekly_price');
    }

    public function getMonthlyPrice(): float
    {
        return (float) $this->getConfigValue('plans/monthly_price');
    }

    public function getYearlyPrice(): float
    {
        return (float) $this->getConfigValue('plans/yearly_price');
    }

    public function getWeeklyLabel(): string
    {
        return (string) ($this->getConfigValue('plans/weekly_label') ?: 'Weekly');
    }

    public function getMonthlyLabel(): string
    {
        return (string) ($this->getConfigValue('plans/monthly_label') ?: 'Monthly');
    }

    public function getYearlyLabel(): string
    {
        return (string) ($this->getConfigValue('plans/yearly_label') ?: 'Yearly');
    }

    public function getCurrencyCode(): string
    {
        return strtoupper((string) ($this->getConfigValue('payment/stripe_currency') ?: 'usd'));
    }

    public function getCurrencySymbol(): string
    {
        $code = $this->getCurrencyCode();
        return $code === 'USD' ? '$' : $code . ' ';
    }

    public function getCheckoutUrl(): string
    {
        return $this->getUrl('etechflow_abandonedcart/license/checkout');
    }

    public function getSettingsUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit/section/etechflow_abandoned_cart');
    }

    public function formatPrice(float $amount): string
    {
        return $this->getCurrencySymbol() . number_format($amount, 0);
    }

    private function getConfigValue(string $path): mixed
    {
        return $this->_scopeConfig->getValue(
            self::CONFIG_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE
        );
    }
}
