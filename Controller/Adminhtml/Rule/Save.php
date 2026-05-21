<?php
/**
 * Etechflow_AbandonedCart - Admin Rule save controller (POST).
 *
 * Receives form submit, normalizes multiselect-as-CSV columns
 * (store_ids, customer_group_ids), validates required fields,
 * persists via the Rule repository, redirects.
 *
 * Save vs Save-and-Continue is detected via the `back` URL param —
 * Magento's form toolbar sets `back=edit` on "Save and Continue Edit".
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\Rule;

use Etechflow\AbandonedCart\Api\Data\RuleInterface;
use Etechflow\AbandonedCart\Api\RuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\RuleFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::rules';

    public function __construct(
        Context $context,
        private readonly RuleRepositoryInterface $ruleRepo,
        private readonly RuleFactory $ruleFactory,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (empty($data)) {
            return $redirect->setPath('*/*/index');
        }

        try {
            $rule = $this->loadOrCreate((int) ($data['rule_id'] ?? 0));
            $this->populate($rule, $data);
            $this->ruleRepo->save($rule);

            $this->messageManager->addSuccessMessage(__('The rule has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['rule_id' => $rule->getRuleId()]);
            }
            return $redirect->setPath('*/*/index');
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The rule no longer exists.'));
            return $redirect->setPath('*/*/index');
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: Rule save failed',
                ['exception' => $e->getMessage(), 'posted' => array_keys($data)]
            );
            $this->messageManager->addErrorMessage(__('Could not save the rule: %1', $e->getMessage()));

            $ruleId = (int) ($data['rule_id'] ?? 0);
            if ($ruleId > 0) {
                return $redirect->setPath('*/*/edit', ['rule_id' => $ruleId]);
            }
            return $redirect->setPath('*/*/new');
        }
    }

    private function loadOrCreate(int $id): RuleInterface
    {
        if ($id > 0) {
            return $this->ruleRepo->getById($id);
        }
        return $this->ruleFactory->create();
    }

    private function populate(RuleInterface $rule, array $data): void
    {
        $rule->setName((string) ($data['name'] ?? ''));
        $rule->setDescription(isset($data['description']) && $data['description'] !== '' ? (string) $data['description'] : null);
        $rule->setIsActive((bool) ($data['is_active'] ?? false));
        $rule->setPriority((int) ($data['priority'] ?? 0));

        $rule->setSendAfterMinutes((int) ($data['send_after_minutes'] ?? 60));
        $rule->setSequenceNumber((int) ($data['sequence_number'] ?? 1));
        $rule->setApplyToGuests((bool) ($data['apply_to_guests'] ?? true));

        $rule->setEmailTemplate((string) ($data['email_template'] ?? 'etechflow_abandoned_cart_default_template'));
        $rule->setEmailSender((string) ($data['email_sender'] ?? 'general'));

        $rule->setStoreIds($this->joinCsv($data['store_ids'] ?? ['0']));
        $rule->setCustomerGroupIds($this->joinCsv($data['customer_group_ids'] ?? ['0']));

        $rule->setMinCartSubtotal($this->normalizeFloat($data['min_cart_subtotal'] ?? null));
        $rule->setMaxCartSubtotal($this->normalizeFloat($data['max_cart_subtotal'] ?? null));
    }

    /**
     * @param array|string $value
     */
    private function joinCsv($value): string
    {
        if (is_array($value)) {
            $filtered = array_values(array_filter(array_map('trim', $value), static fn($v) => $v !== ''));
            return empty($filtered) ? '0' : implode(',', $filtered);
        }
        $str = trim((string) $value);
        return $str === '' ? '0' : $str;
    }

    private function normalizeFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }
}
