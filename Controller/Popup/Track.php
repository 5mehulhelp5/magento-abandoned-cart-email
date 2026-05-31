<?php
/**
 * Etechflow_AbandonedCart - Records a popup impression.
 *
 * URL: POST `/etechflow_abandonedcart/popup/track/`
 *   body: rule_id, device_type
 *
 * Called by frontend JS the instant the popup is rendered (before the user
 * dismisses/accepts). Returns the new impression_id so a subsequent Apply
 * call can mark the same row as accepted.
 *
 * Implements CsrfAwareActionInterface to skip form_key validation —
 * we expect the call from popup JS which doesn't carry a form key. Input
 * is validated by repo + matcher; abuse risk is low (worst case: spammy
 * impression rows).
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Popup;

use Etechflow\AbandonedCart\Api\Data\PopupImpressionInterface;
use Etechflow\AbandonedCart\Api\PopupImpressionRepositoryInterface;
use Etechflow\AbandonedCart\Api\PopupRuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Etechflow\AbandonedCart\Model\PopupImpressionFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Track implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly PopupRuleRepositoryInterface $popupRuleRepo,
        private readonly PopupImpressionRepositoryInterface $impressionRepo,
        private readonly PopupImpressionFactory $impressionFactory,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly SessionManagerInterface $sessionManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isEnabled() || !$this->licenseValidator->isValid()) {
            return $result->setData(['success' => false, 'message' => 'disabled']);
        }

        $span = Profiler::start('Etechflow_ABC_PopupTrack');

        try {
            $ruleId     = (int) $this->request->getParam('rule_id');
            $deviceType = $this->normalizeDeviceType((string) $this->request->getParam('device_type', ''));

            if ($ruleId <= 0) {
                return $result->setData(['success' => false, 'message' => 'invalid_rule_id']);
            }

            try {
                $rule = $this->popupRuleRepo->getById($ruleId);
            } catch (NoSuchEntityException) {
                return $result->setData(['success' => false, 'message' => 'rule_not_found']);
            }

            if (!$rule->isActive()) {
                return $result->setData(['success' => false, 'message' => 'rule_inactive']);
            }

            $quote      = $this->checkoutSession->getQuote();
            $quoteId    = $quote->getId() ? (int) $quote->getId() : null;
            $isLoggedIn = $this->customerSession->isLoggedIn();
            $customerId    = $isLoggedIn ? (int) $this->customerSession->getCustomerId() : null;
            $customerEmail = $isLoggedIn ? (string) $this->customerSession->getCustomer()->getEmail() : null;

            $impression = $this->impressionFactory->create();
            $impression->setPopupRuleId($ruleId);
            $impression->setCustomerId($customerId);
            $impression->setCustomerEmail($customerEmail);
            $impression->setQuoteId($quoteId);
            $impression->setSessionId((string) $this->sessionManager->getSessionId());
            $impression->setStoreId((int) $this->storeManager->getStore()->getId());
            $impression->setDeviceType($deviceType);
            $impression->setShownAt($this->dateTime->gmtDate());

            $this->impressionRepo->save($impression);

            return $result->setData([
                'success'       => true,
                'impression_id' => (int) $impression->getImpressionId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Etechflow_AbandonedCart: popup track failed',
                ['exception' => $e->getMessage()]
            );
            return $result->setData(['success' => false, 'message' => 'server_error']);
        } finally {
            Profiler::stop($span);
        }
    }

    private function normalizeDeviceType(string $raw): string
    {
        $raw = strtolower(trim($raw));
        return in_array($raw, [
            PopupImpressionInterface::DEVICE_DESKTOP,
            PopupImpressionInterface::DEVICE_MOBILE,
            PopupImpressionInterface::DEVICE_TABLET,
        ], true) ? $raw : PopupImpressionInterface::DEVICE_DESKTOP;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
