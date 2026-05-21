<?php
/**
 * Etechflow_AbandonedCart - Admin per-cart detail controller.
 *
 * URL: /admin/etechflow_abandonedcart/cart/view/id/123
 *
 * Loads the abandoned-cart record, registers it for the layout's blocks
 * (Phase 17/18 may add forms, email-history widget, etc.), and renders
 * the detail page.
 *
 * Missing / invalid id → redirect to grid with error message.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\Cart;

use Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::carts';

    public const REGISTRY_CURRENT_CART = 'etechflow_current_abandoned_cart';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly Registry $registry,
        private readonly AbandonedCartRepositoryInterface $cartRepo,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Cart ID is required.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $cart = $this->cartRepo->getById($id);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The requested cart was not found.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $this->registry->register(self::REGISTRY_CURRENT_CART, $cart, true);

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Etechflow_AbandonedCart::carts');
        $page->getConfig()->getTitle()->prepend(
            __('Abandoned Cart #%1 — %2', $cart->getEntityId(), $cart->getCustomerEmail())
        );
        $page->addBreadcrumb(__('ETechFlow Abandoned Cart'), __('ETechFlow Abandoned Cart'));
        $page->addBreadcrumb(__('Abandoned Carts'), __('Abandoned Carts'));
        $page->addBreadcrumb(__('View Cart'), __('View Cart'));
        return $page;
    }
}
