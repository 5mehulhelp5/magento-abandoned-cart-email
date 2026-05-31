<?php
/**
 * Etechflow_AbandonedCart - Admin Popup Rules grid listing controller.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\PopupRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::popup_rules';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Etechflow_AbandonedCart::popup_rules');
        $page->getConfig()->getTitle()->prepend(__('Popup Rules'));
        $page->addBreadcrumb(__('ETechFlow Abandoned Cart'), __('ETechFlow Abandoned Cart'));
        $page->addBreadcrumb(__('Popup Rules'), __('Popup Rules'));
        return $page;
    }
}
