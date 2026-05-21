<?php
/**
 * Etechflow_AbandonedCart - Manual "Send Now" admin action.
 *
 * Triggered from the per-row Actions dropdown OR from the per-cart view
 * page. Bypasses the cron scheduler — queues an email_log row for the
 * given cart immediately. SendQueuedEmails cron (or the next admin click)
 * picks it up on the next tick.
 *
 * Uses the FIRST active rule as the template source (highest priority
 * rule whose sequence_number is one past what the cart has already
 * received). If no active rule matches, falls back to the configured
 * default template and creates a synthetic log with rule_id=null.
 *
 * Triggers the configured default template so the merchant doesn't have
 * to pick. Future enhancement: let the merchant choose which rule to
 * fire from a dropdown on the View page.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\Cart;

use Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Api\EmailLogRepositoryInterface;
use Etechflow\AbandonedCart\Api\RuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\EmailLogFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;

class SendNow extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::carts';

    public function __construct(
        Context $context,
        private readonly AbandonedCartRepositoryInterface $cartRepo,
        private readonly RuleRepositoryInterface $ruleRepo,
        private readonly EmailLogRepositoryInterface $emailLogRepo,
        private readonly EmailLogFactory $emailLogFactory,
        private readonly Config $config,
        private readonly DateTime $dateTime,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('id');

        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Cart ID is required.'));
            return $redirect->setPath('*/*/index');
        }

        try {
            $cart = $this->cartRepo->getById($id);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The requested cart was not found.'));
            return $redirect->setPath('*/*/index');
        }

        $storeId = $cart->getStoreId();
        $template = $this->resolveTemplate($cart->getEmailsSent(), $storeId);
        $sequenceNumber = $cart->getEmailsSent() + 1;
        $ruleId = $this->resolveRuleId($sequenceNumber, $storeId);

        $now = $this->dateTime->gmtDate();

        /** @var EmailLogInterface $log */
        $log = $this->emailLogFactory->create();
        $log->setCartId((int) $cart->getEntityId());
        $log->setRuleId($ruleId);
        $log->setRecipientEmail($cart->getCustomerEmail());
        $log->setEmailTemplate($template);
        $log->setSequenceNumber($sequenceNumber);
        $log->setStatus(EmailLogInterface::STATUS_QUEUED);
        $log->setOpenCount(0);
        $log->setClickCount(0);
        $log->setCreatedAt($now);
        $this->emailLogRepo->save($log);

        $cart->setEmailsSent($sequenceNumber);
        $cart->setLastEmailSentAt($now);
        $this->cartRepo->save($cart);

        $this->messageManager->addSuccessMessage(
            __('A reminder email has been queued for %1. The SendQueuedEmails cron will deliver it on the next tick (typically within 5 minutes).', $cart->getCustomerEmail())
        );

        return $redirect->setPath('*/*/view', ['id' => $id]);
    }

    private function resolveTemplate(int $alreadySent, int $storeId): string
    {
        return $this->config->getDefaultEmailTemplate($storeId);
    }

    /**
     * Pick the highest-priority active rule whose sequence_number equals
     * the next reminder number for this cart. Returns null if none match
     * — the log row is still created (manual send overrides rule-matching),
     * just without an attribution link.
     */
    private function resolveRuleId(int $sequenceNumber, int $storeId): ?int
    {
        foreach ($this->ruleRepo->getActiveRules($storeId) as $rule) {
            if ($rule->getSequenceNumber() === $sequenceNumber) {
                return (int) $rule->getRuleId();
            }
        }
        return null;
    }
}
