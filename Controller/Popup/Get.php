<?php
/**
 * Etechflow_AbandonedCart - Returns matching active popup rules as JSON.
 *
 * URL: GET `/etechflow_abandonedcart/popup/get/?page_scope=cart`
 *
 * Called from frontend JS on every page load (after license + extension
 * enabled check). Pipeline:
 *
 *   1. Repo filters by is_active + page_scope + store_id (CSV match)
 *   2. PopupRuleMatcher filters by customer_group + guest-flag + subtotal
 *      range + frequency caps + impression caps
 *   3. Returns thin JSON suitable for the popup component to render —
 *      no DB IDs leak beyond rule_id, no admin-only fields exposed
 *
 * Output shape:
 *   { "rules": [{ "rule_id": 1, "trigger_type": "exit_intent", ... }, ...] }
 *
 * Empty array is the normal "no popup to show" state — don't 404.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Popup;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Etechflow\AbandonedCart\Api\PopupRuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Etechflow\AbandonedCart\Model\Service\PopupRuleMatcher;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Get implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly PopupRuleRepositoryInterface $popupRuleRepo,
        private readonly PopupRuleMatcher $matcher,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly SessionManagerInterface $sessionManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isEnabled() || !$this->licenseValidator->isValid()) {
            return $result->setData(['rules' => []]);
        }

        $span = Profiler::start('Etechflow_ABC_PopupGet');

        try {
            $pageScope = (string) $this->request->getParam('page_scope', PopupRuleInterface::SCOPE_ALL);
            $storeId   = (int) $this->storeManager->getStore()->getId();

            $rules = $this->popupRuleRepo->getActiveRules($storeId, $pageScope);
            if (empty($rules)) {
                return $result->setData(['rules' => []]);
            }

            $quote         = $this->checkoutSession->getQuote();
            $cartSubtotal  = $quote->getId() ? (float) $quote->getSubtotal() : null;
            $isLoggedIn    = $this->customerSession->isLoggedIn();
            $customerEmail = $isLoggedIn ? (string) $this->customerSession->getCustomer()->getEmail() : null;
            $groupId       = (int) $this->customerSession->getCustomerGroupId();
            $sessionId     = (string) $this->sessionManager->getSessionId();

            $matched = $this->matcher->filter(
                $rules,
                $groupId,
                !$isLoggedIn,
                $customerEmail,
                $sessionId,
                $cartSubtotal
            );

            return $result->setData(['rules' => array_map([$this, 'toPayload'], $matched)]);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Etechflow_AbandonedCart: popup get failed',
                ['exception' => $e->getMessage()]
            );
            return $result->setData(['rules' => []]);
        } finally {
            Profiler::stop($span);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(PopupRuleInterface $rule): array
    {
        return [
            'rule_id'        => (int) $rule->getRuleId(),
            'trigger_type'   => $rule->getTriggerType(),
            'trigger_value'  => $rule->getTriggerValue(),
            'page_scope'     => $rule->getPageScope(),
            'headline'       => $rule->getPopupHeadline(),
            'body'           => $rule->getPopupBody(),
            'cta_text'       => $rule->getPopupCtaText(),
            'image_url'      => $rule->getPopupImageUrl(),
            'has_discount'   => $rule->getSalesRuleId() !== null && $rule->getSalesRuleId() > 0,
            'frequency'      => $rule->getFrequency(),
        ];
    }
}
