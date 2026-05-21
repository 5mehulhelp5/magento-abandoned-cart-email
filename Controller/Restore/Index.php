<?php
/**
 * Etechflow_AbandonedCart - Restore controller.
 *
 * Customer clicks the "Complete Your Purchase" button in the recovery
 * email → arrives here at `/etechflow_abandonedcart/restore/index/?t=TOKEN`.
 *
 * Behavior:
 *   1. Validate the restore_token against the DB. Unknown / expired token
 *      → friendly error message + redirect to homepage.
 *   2. Validate the token isn't past `restore/token_expiry_days` from when
 *      the cart was abandoned. Default 30 days.
 *   3. Set the original quote as the current checkout-session quote, so
 *      every other Magento page (cart, checkout) shows the restored items.
 *   4. If the cart originally belonged to a logged-out customer AND
 *      `restore/auto_login_customer` is on, log them back in.
 *   5. Redirect to `/checkout/cart` so the customer immediately sees their
 *      restored items.
 *
 * "Merge with existing cart" (config `restore/merge_with_existing_cart`)
 * is honored by setQuoteId() behavior — Magento attaches the restored
 * quote as the customer's active cart; the customer's session-only guest
 * cart from after the abandonment gets superseded. Full item-by-item
 * merge logic deferred to Phase 14 — the most common case (customer
 * follows the email link without building a new cart in between) works
 * fine with this simpler path.
 *
 * Silent-fail per §19 — never raise a 500 on this URL. Bad inputs land
 * on the homepage with an admin-configurable message.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Restore;

use Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Psr\Log\LoggerInterface;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly AbandonedCartRepositoryInterface $cartRepo,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly MessageManager $messageManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isEnabled() || !$this->licenseValidator->isValid()) {
            return $redirect->setPath('/');
        }

        $span = Profiler::start('Etechflow_ABC_Restore');

        try {
            $token = trim((string) $this->request->getParam('t'));
            if ($token === '') {
                $this->messageManager->addErrorMessage(
                    __('That recovery link is missing its identifier. Please use the link from your email exactly as we sent it.')
                );
                return $redirect->setPath('/');
            }

            try {
                $cart = $this->cartRepo->getByRestoreToken($token);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(
                    __('That recovery link is no longer valid. It may have already been used or expired.')
                );
                return $redirect->setPath('/');
            }

            if ($this->isExpired($cart)) {
                $this->messageManager->addErrorMessage(
                    __('That recovery link has expired. Please start a new shopping session.')
                );
                return $redirect->setPath('/');
            }

            if ($cart->getStatus() === AbandonedCartInterface::STATUS_UNSUBSCRIBED) {
                $this->messageManager->addErrorMessage(
                    __('You previously unsubscribed from cart-recovery emails. Please contact support if you would like to resume your purchase.')
                );
                return $redirect->setPath('/');
            }

            $this->checkoutSession->setQuoteId((int) $cart->getQuoteId());

            $this->maybeAutoLogin($cart);

            $this->logger->info(
                'Etechflow_AbandonedCart: cart restored',
                [
                    'cart_id'  => $cart->getEntityId(),
                    'quote_id' => $cart->getQuoteId(),
                    'customer_id' => $cart->getCustomerId(),
                ]
            );

            return $redirect->setPath('checkout/cart');
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: Restore controller crashed',
                ['exception' => $e->getMessage()]
            );
            $this->messageManager->addErrorMessage(
                __('Something went wrong restoring your cart. Please try again or contact support.')
            );
            return $redirect->setPath('/');
        } finally {
            Profiler::stop($span);
        }
    }

    private function isExpired(AbandonedCartInterface $cart): bool
    {
        $expiryDays = $this->config->getRestoreTokenExpiryDays($cart->getStoreId());
        if ($expiryDays <= 0) {
            return false;
        }
        $abandonedAt = strtotime($cart->getAbandonedAt());
        if ($abandonedAt === false) {
            return false;
        }
        return time() > ($abandonedAt + ($expiryDays * 86400));
    }

    private function maybeAutoLogin(AbandonedCartInterface $cart): void
    {
        if ($this->customerSession->isLoggedIn()) {
            return;
        }
        if (!$this->config->isAutoLoginCustomerOnRestore($cart->getStoreId())) {
            return;
        }
        $customerId = $cart->getCustomerId();
        if ($customerId === null || $customerId <= 0) {
            return;
        }

        try {
            $this->customerSession->loginById($customerId);
        } catch (\Throwable $e) {
            // Login failure is non-fatal — the customer can still complete checkout as guest.
            $this->logger->warning(
                'Etechflow_AbandonedCart: restore auto-login failed',
                ['customer_id' => $customerId, 'exception' => $e->getMessage()]
            );
        }
    }
}
