<?php
/**
 * Etechflow_AbandonedCart - Admin Popup Rule edit page controller.
 *
 * Also serves the "new" use case via Forward from NewAction. When no
 * `rule_id` URL param is present, the form's DataProvider returns
 * empty → form renders blank. With `rule_id`, the DataProvider loads
 * that rule and pre-fills the form.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\PopupRule;

use Etechflow\AbandonedCart\Api\PopupRuleRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::popup_rules';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly PopupRuleRepositoryInterface $popupRuleRepo,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('rule_id');
        $isNew = ($id <= 0);

        if (!$isNew) {
            try {
                $this->popupRuleRepo->getById($id);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('The requested popup rule was not found.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Etechflow_AbandonedCart::popup_rules');
        $page->getConfig()->getTitle()->prepend(
            $isNew ? __('New Popup Rule') : __('Edit Popup Rule #%1', $id)
        );
        $page->addBreadcrumb(__('ETechFlow Abandoned Cart'), __('ETechFlow Abandoned Cart'));
        $page->addBreadcrumb(__('Popup Rules'), __('Popup Rules'));
        $page->addBreadcrumb(
            $isNew ? __('New Popup Rule') : __('Edit Popup Rule'),
            $isNew ? __('New Popup Rule') : __('Edit Popup Rule')
        );
        return $page;
    }
}
