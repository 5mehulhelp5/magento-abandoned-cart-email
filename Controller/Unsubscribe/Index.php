<?php
/**
 * Etechflow_AbandonedCart - Unsubscribe controller.
 *
 * URL: `/etechflow_abandonedcart/unsubscribe/index/?t=TOKEN`
 *
 * One-click unsubscribe per CAN-SPAM compliance. Flips the cart's status
 * to UNSUBSCRIBED so the SendReminders cron skips it forever. We do NOT
 * delete the row (we want to keep stats for the Reports dashboard +
 * prevent the SAME customer from being re-tracked on a future cart save
 * — would re-enter the funnel right after they asked out).
 *
 * Returns a Page result so the storefront theme can render a friendly
 * confirmation page. Layout XML + .phtml live in Phase 14 (Block +
 * ViewModel + Luma/Hyvä templates). Default Magento behavior until then:
 * a blank page with header + footer, no body content. Still functional
 * (the unsubscribe IS recorded) — just plain looking.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Unsubscribe;

use Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

class Index implements HttpGetActionInterface
{
    public const REGISTRY_UNSUBSCRIBED_CART = 'etechflow_unsubscribed_cart';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly AbandonedCartRepositoryInterface $cartRepo,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled()) {
            return $this->redirectFactory->create()->setPath('/');
        }

        $span = Profiler::start('Etechflow_ABC_Unsubscribe');

        try {
            $token = trim((string) $this->request->getParam('t'));
            if ($token === '') {
                return $this->redirectFactory->create()->setPath('/');
            }

            try {
                $cart = $this->cartRepo->getByRestoreToken($token);
            } catch (NoSuchEntityException) {
                return $this->renderConfirmationPage(false);
            }

            if ($cart->getStatus() !== AbandonedCartInterface::STATUS_UNSUBSCRIBED) {
                $cart->setStatus(AbandonedCartInterface::STATUS_UNSUBSCRIBED);
                $this->cartRepo->save($cart);
                $this->logger->info(
                    'Etechflow_AbandonedCart: customer unsubscribed',
                    [
                        'cart_id'  => $cart->getEntityId(),
                        'quote_id' => $cart->getQuoteId(),
                        'email'    => $cart->getCustomerEmail(),
                    ]
                );
            }

            return $this->renderConfirmationPage(true, $cart);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: Unsubscribe controller crashed',
                ['exception' => $e->getMessage()]
            );
            return $this->renderConfirmationPage(false);
        } finally {
            Profiler::stop($span);
        }
    }

    private function renderConfirmationPage(bool $success, ?AbandonedCartInterface $cart = null): ResultInterface
    {
        $this->registry->register(
            self::REGISTRY_UNSUBSCRIBED_CART,
            [
                'success' => $success,
                'cart'    => $cart,
            ],
            true
        );

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string) __('Unsubscribe Confirmation'));
        return $page;
    }
}
