<?php
/**
 * Etechflow_AbandonedCart - Post-Stripe activation result block.
 *
 * Reads the payload registered by the Activated controller from
 * `Magento\Framework\Registry` and exposes it to the template.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block\Adminhtml\License;

use Etechflow\AbandonedCart\Controller\Adminhtml\License\Activated as ActivatedController;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;

class Activated extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        $data = $this->registry->registry(ActivatedController::REGISTRY_KEY);
        return is_array($data) ? $data : [];
    }

    public function getLicenseKey(): ?string
    {
        return $this->getPayload()['license_key'] ?? null;
    }

    public function getErrorMessage(): ?string
    {
        return $this->getPayload()['error'] ?? null;
    }

    public function getPlan(): string
    {
        return (string) ($this->getPayload()['plan'] ?? '');
    }

    public function getSettingsUrl(): string
    {
        return (string) ($this->getPayload()['settings_url'] ?? $this->getUrl('adminhtml/system_config/edit/section/etechflow_abandoned_cart'));
    }

    public function getManagementUrl(): string
    {
        return (string) ($this->getPayload()['management_url'] ?? $this->getUrl('etechflow_abandonedcart/cart/index'));
    }

    public function getGateUrl(): string
    {
        return $this->getUrl('etechflow_abandonedcart/license/gate');
    }

    public function isSuccess(): bool
    {
        return $this->getErrorMessage() === null && $this->getLicenseKey() !== null;
    }
}
