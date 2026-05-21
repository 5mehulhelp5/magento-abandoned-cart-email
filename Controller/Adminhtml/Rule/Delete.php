<?php
/**
 * Etechflow_AbandonedCart - Admin Rule delete controller.
 *
 * Confirms via the action-column's `confirm` dialog (browser-side) before
 * hitting this endpoint. Triggers the rule repository's delete which also
 * cleans up email_log rows via the FK's ON DELETE SET NULL.
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
use Psr\Log\LoggerInterface;

class Delete extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::rules';

    public function __construct(
        Context $context,
        private readonly RuleRepositoryInterface $ruleRepo,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('rule_id');

        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Rule ID is required.'));
            return $redirect->setPath('*/*/index');
        }

        try {
            $this->ruleRepo->deleteById($id);
            $this->messageManager->addSuccessMessage(__('The rule has been deleted.'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The rule no longer exists.'));
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: Rule delete failed',
                ['rule_id' => $id, 'exception' => $e->getMessage()]
            );
            $this->messageManager->addErrorMessage(__('Could not delete the rule: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/index');
    }
}
