<?php
/**
 * Etechflow_AbandonedCart - Admin Popup Rule save controller (POST).
 *
 * Receives form submit, normalizes multiselect-as-CSV columns
 * (store_ids, customer_group_ids), validates required fields,
 * persists via the PopupRule repository, redirects.
 *
 * Save vs Save-and-Continue is detected via the `back` URL param —
 * Magento's form toolbar sets `back=edit` on "Save and Continue Edit".
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\PopupRule;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Etechflow\AbandonedCart\Api\PopupRuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\PopupRuleFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::popup_rules';

    public function __construct(
        Context $context,
        private readonly PopupRuleRepositoryInterface $popupRuleRepo,
        private readonly PopupRuleFactory $popupRuleFactory,
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
            $this->popupRuleRepo->save($rule);

            $this->messageManager->addSuccessMessage(__('The popup rule has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['rule_id' => $rule->getRuleId()]);
            }
            return $redirect->setPath('*/*/index');
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The popup rule no longer exists.'));
            return $redirect->setPath('*/*/index');
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: PopupRule save failed',
                ['exception' => $e->getMessage(), 'posted' => array_keys($data)]
            );
            $this->messageManager->addErrorMessage(__('Could not save the popup rule: %1', $e->getMessage()));

            $ruleId = (int) ($data['rule_id'] ?? 0);
            if ($ruleId > 0) {
                return $redirect->setPath('*/*/edit', ['rule_id' => $ruleId]);
            }
            return $redirect->setPath('*/*/new');
        }
    }

    private function loadOrCreate(int $id): PopupRuleInterface
    {
        if ($id > 0) {
            return $this->popupRuleRepo->getById($id);
        }
        return $this->popupRuleFactory->create();
    }

    private function populate(PopupRuleInterface $rule, array $data): void
    {
        $rule->setName((string) ($data['name'] ?? ''));
        $rule->setDescription(isset($data['description']) && $data['description'] !== '' ? (string) $data['description'] : null);
        $rule->setIsActive((bool) ($data['is_active'] ?? false));
        $rule->setPriority((int) ($data['priority'] ?? 10));

        $rule->setTriggerType((string) ($data['trigger_type'] ?? PopupRuleInterface::TRIGGER_EXIT_INTENT));
        $rule->setTriggerValue($this->normalizeInt($data['trigger_value'] ?? null));
        $rule->setPageScope((string) ($data['page_scope'] ?? PopupRuleInterface::SCOPE_ALL));

        $rule->setPopupHeadline((string) ($data['popup_headline'] ?? ''));
        $rule->setPopupBody(isset($data['popup_body']) && $data['popup_body'] !== '' ? (string) $data['popup_body'] : null);
        $rule->setPopupCtaText((string) ($data['popup_cta_text'] ?? 'Get My Discount'));
        $rule->setPopupImageUrl(isset($data['popup_image_url']) && $data['popup_image_url'] !== '' ? (string) $data['popup_image_url'] : null);

        $rule->setSalesRuleId($this->normalizeInt($data['sales_rule_id'] ?? null));

        $rule->setStoreIds($this->joinCsv($data['store_ids'] ?? ['0']));
        $rule->setCustomerGroupIds($this->joinCsv($data['customer_group_ids'] ?? ['0']));

        $rule->setMinCartSubtotal($this->normalizeFloat($data['min_cart_subtotal'] ?? null));
        $rule->setMaxCartSubtotal($this->normalizeFloat($data['max_cart_subtotal'] ?? null));

        $rule->setApplyToGuests((bool) ($data['apply_to_guests'] ?? true));
        $rule->setFrequency((string) ($data['frequency'] ?? PopupRuleInterface::FREQUENCY_ONCE_PER_SESSION));
        $rule->setMaxImpressionsPerCustomer((int) ($data['max_impressions_per_customer'] ?? 3));
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

    private function normalizeInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }
}
