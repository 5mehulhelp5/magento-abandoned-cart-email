<?php
/**
 * Etechflow_AbandonedCart - Admin Rule edit page controller.
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

namespace Etechflow\AbandonedCart\Controller\Adminhtml\Rule;

use Etechflow\AbandonedCart\Api\RuleRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::rules';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly RuleRepositoryInterface $ruleRepo,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('rule_id');
        $isNew = ($id <= 0);

        if (!$isNew) {
            try {
                $this->ruleRepo->getById($id);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('The requested rule was not found.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Etechflow_AbandonedCart::rules');
        $page->getConfig()->getTitle()->prepend(
            $isNew ? __('New Email Rule') : __('Edit Email Rule #%1', $id)
        );
        $page->addBreadcrumb(__('ETechFlow Abandoned Cart'), __('ETechFlow Abandoned Cart'));
        $page->addBreadcrumb(__('Email Rules'), __('Email Rules'));
        $page->addBreadcrumb(
            $isNew ? __('New Rule') : __('Edit Rule'),
            $isNew ? __('New Rule') : __('Edit Rule')
        );
        return $page;
    }
}
