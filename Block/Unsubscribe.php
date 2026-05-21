<?php
/**
 * Etechflow_AbandonedCart - Luma Block for the unsubscribe confirmation page.
 *
 * Reads the registry payload set by
 * [[Etechflow\AbandonedCart\Controller\Unsubscribe\Index]] and exposes it to
 * the Luma template via `$block->...` accessors. ViewModel-equivalent for
 * Hyvä lives at [[Etechflow\AbandonedCart\ViewModel\Unsubscribe]].
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Controller\Unsubscribe\Index as UnsubscribeAction;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Unsubscribe extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isSuccess(): bool
    {
        return (bool) ($this->getRegistryData()['success'] ?? false);
    }

    public function getCart(): ?AbandonedCartInterface
    {
        $cart = $this->getRegistryData()['cart'] ?? null;
        return $cart instanceof AbandonedCartInterface ? $cart : null;
    }

    public function getCustomerFirstname(): string
    {
        return (string) ($this->getCart()?->getCustomerFirstname() ?? '');
    }

    public function getCustomerEmail(): string
    {
        return (string) ($this->getCart()?->getCustomerEmail() ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function getRegistryData(): array
    {
        $data = $this->registry->registry(UnsubscribeAction::REGISTRY_UNSUBSCRIBED_CART);
        return is_array($data) ? $data : [];
    }
}
