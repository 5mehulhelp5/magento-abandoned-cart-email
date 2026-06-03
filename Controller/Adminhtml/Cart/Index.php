<?php
/**
 * Etechflow_AbandonedCart - Admin Carts grid listing controller.
 *
 * Renders the UI Component grid at
 * Marketing → ETechFlow Abandoned Cart → Abandoned Carts.
 *
 * The ADMIN_RESOURCE constant gates entry — Magento's framework checks
 * the current admin user has that ACL node before calling execute().
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\Cart;

use Etechflow\AbandonedCart\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::carts';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly LicenseValidator $licenseValidator,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->licenseValidator->isValid()) {
            return $this->resultFactory
                ->create(ResultFactory::TYPE_REDIRECT)
                ->setPath('etechflow_abandonedcart/license/gate');
        }

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Etechflow_AbandonedCart::carts');
        $page->getConfig()->getTitle()->prepend(__('Abandoned Carts'));
        $page->addBreadcrumb(__('ETechFlow Abandoned Cart'), __('ETechFlow Abandoned Cart'));
        $page->addBreadcrumb(__('Abandoned Carts'), __('Abandoned Carts'));
        return $page;
    }
}
