<?php
/**
 * Etechflow_AbandonedCart - Hyvä ViewModel for the unsubscribe confirmation page.
 *
 * Hyvä's idiom prefers `Magento\Framework\View\Element\Block\ArgumentInterface`
 * implementors over Blocks. The template calls `$viewModel->...` (not
 * `$block->...`). Same data surface as [[Etechflow\AbandonedCart\Block\Unsubscribe]]
 * — duplicated intentionally per §9 (Luma + Hyvä first-class).
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\ViewModel;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Controller\Unsubscribe\Index as UnsubscribeAction;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Unsubscribe implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
    ) {
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
