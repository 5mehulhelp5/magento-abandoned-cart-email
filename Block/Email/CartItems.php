<?php
/**
 * Etechflow_AbandonedCart - Cart-items email block.
 *
 * Embedded inside the abandoned-cart email templates via Magento's
 * `{{block class="..." cart=$cart}}` directive. Renders the customer's
 * cart contents (image + name + qty + line total) as a self-contained
 * table fragment.
 *
 * Why a Block + .phtml partial rather than inline HTML in the email body:
 *   - Foreach loops aren't supported in Magento's email-directive language
 *     (`{{var}}`, `{{trans}}`, `{{depend}}` only). A block is the canonical
 *     way to iterate.
 *   - Future merchants can override the partial in their own theme without
 *     touching the email template itself.
 *
 * The block receives the AbandonedCart record as a directive argument
 * (`cart=$cart`). From there it loads the underlying Magento quote so the
 * partial can walk `$block->getQuoteItems()` and render product image,
 * name, qty, and row total.
 *
 * Silent-fail per §19: if the quote was deleted (rare but possible — e.g.,
 * the customer cleared their session and Magento garbage-collected the
 * quote before our cron caught up), we return an empty array instead of
 * throwing. The email still sends but the items table is empty rather
 * than a 500 page.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block\Email;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Psr\Log\LoggerInterface;

class CartItems extends Template
{
    public function __construct(
        Context $context,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCart(): ?AbandonedCartInterface
    {
        $cart = $this->getData('cart');
        return $cart instanceof AbandonedCartInterface ? $cart : null;
    }

    /**
     * @return CartItemInterface[]
     */
    public function getQuoteItems(): array
    {
        $cart = $this->getCart();
        if ($cart === null) {
            return [];
        }

        try {
            $quote = $this->quoteRepository->get($cart->getQuoteId());
            return $quote->getAllVisibleItems();
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Etechflow_AbandonedCart: cart-items block could not load quote',
                [
                    'cart_id'   => $cart->getEntityId(),
                    'quote_id'  => $cart->getQuoteId(),
                    'exception' => $e->getMessage(),
                ]
            );
            return [];
        }
    }

    public function getCurrencyCode(): string
    {
        return (string) ($this->getCart()?->getCurrencyCode() ?? '');
    }

    public function formatPrice(float $amount): string
    {
        $currency = $this->getCurrencyCode();
        if ($currency === '') {
            return number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }

    public function getProductImageUrl(CartItemInterface $item): string
    {
        $product = $item->getProduct();
        if ($product === null) {
            return '';
        }
        $image = $product->getData('thumbnail') ?: $product->getData('image');
        if (!$image || $image === 'no_selection') {
            return '';
        }
        $base = (string) $this->_storeManager->getStore()->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
        );
        return rtrim($base, '/') . '/catalog/product' . $image;
    }
}
