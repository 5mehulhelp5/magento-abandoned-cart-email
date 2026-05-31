<?php
/**
 * Etechflow_AbandonedCart - Admin Popup Rule delete controller.
 *
 * Confirms via the action-column's `confirm` dialog (browser-side) before
 * hitting this endpoint. Triggers the repository's delete which also
 * cleans up impression rows via the FK's ON DELETE CASCADE.
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
use Psr\Log\LoggerInterface;

class Delete extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::popup_rules';

    public function __construct(
        Context $context,
        private readonly PopupRuleRepositoryInterface $popupRuleRepo,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('rule_id');

        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Popup rule ID is required.'));
            return $redirect->setPath('*/*/index');
        }

        try {
            $this->popupRuleRepo->deleteById($id);
            $this->messageManager->addSuccessMessage(__('The popup rule has been deleted.'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The popup rule no longer exists.'));
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: PopupRule delete failed',
                ['rule_id' => $id, 'exception' => $e->getMessage()]
            );
            $this->messageManager->addErrorMessage(__('Could not delete the popup rule: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/index');
    }
}
