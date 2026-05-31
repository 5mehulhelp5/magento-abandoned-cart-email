<?php
/**
 * Etechflow_AbandonedCart - Customer clicked the popup CTA → generate coupon,
 * apply to current quote, mark the impression as accepted.
 *
 * URL: POST `/etechflow_abandonedcart/popup/apply/`
 *   body: rule_id, impression_id
 *
 * Pipeline:
 *   1. Validate impression belongs to current session (prevents another
 *      visitor's impression_id from being used to mint a fresh coupon).
 *   2. Skip if the impression is already marked accepted — idempotency.
 *   3. PopupCouponGenerator creates a single-use code attached to the
 *      popup rule's linked Magento Cart Price Rule.
 *   4. Coupon is applied to the customer's current quote via setCouponCode +
 *      collectTotals + repo save.
 *   5. Impression row updated: accepted_at, coupon_code_generated.
 *
 * Failure modes return success=false with a machine-readable `message`
 * key so the popup JS can show a friendly error and continue.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Popup;

use Etechflow\AbandonedCart\Api\PopupImpressionRepositoryInterface;
use Etechflow\AbandonedCart\Api\PopupRuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Etechflow\AbandonedCart\Model\Service\PopupCouponGenerator;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class Apply implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly PopupRuleRepositoryInterface $popupRuleRepo,
        private readonly PopupImpressionRepositoryInterface $impressionRepo,
        private readonly PopupCouponGenerator $couponGenerator,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepo,
        private readonly SessionManagerInterface $sessionManager,
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

        $span = Profiler::start('Etechflow_ABC_PopupApply');

        try {
            $ruleId       = (int) $this->request->getParam('rule_id');
            $impressionId = (int) $this->request->getParam('impression_id');

            if ($ruleId <= 0 || $impressionId <= 0) {
                return $result->setData(['success' => false, 'message' => 'invalid_input']);
            }

            try {
                $rule = $this->popupRuleRepo->getById($ruleId);
            } catch (NoSuchEntityException) {
                return $result->setData(['success' => false, 'message' => 'rule_not_found']);
            }

            $salesRuleId = $rule->getSalesRuleId();
            if ($salesRuleId === null || $salesRuleId <= 0) {
                return $result->setData(['success' => false, 'message' => 'no_discount_linked']);
            }

            try {
                $impression = $this->impressionRepo->getById($impressionId);
            } catch (NoSuchEntityException) {
                return $result->setData(['success' => false, 'message' => 'impression_not_found']);
            }

            $currentSession = (string) $this->sessionManager->getSessionId();
            if ($impression->getSessionId() !== $currentSession) {
                $this->logger->warning(
                    'Etechflow_AbandonedCart: popup apply session mismatch',
                    ['impression_id' => $impressionId, 'rule_id' => $ruleId]
                );
                return $result->setData(['success' => false, 'message' => 'session_mismatch']);
            }

            if ($impression->getAcceptedAt() !== null && $impression->getCouponCodeGenerated() !== null) {
                return $result->setData([
                    'success'     => true,
                    'coupon_code' => $impression->getCouponCodeGenerated(),
                    'reused'      => true,
                ]);
            }

            $couponCode = $this->couponGenerator->generateForRule((int) $salesRuleId);

            $this->applyToCurrentQuote($couponCode);

            $impression->setAcceptedAt($this->dateTime->gmtDate());
            $impression->setCouponCodeGenerated($couponCode);
            $this->impressionRepo->save($impression);

            return $result->setData([
                'success'     => true,
                'coupon_code' => $couponCode,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: popup apply failed',
                [
                    'rule_id'       => $this->request->getParam('rule_id'),
                    'impression_id' => $this->request->getParam('impression_id'),
                    'exception'     => $e->getMessage(),
                ]
            );
            return $result->setData(['success' => false, 'message' => 'server_error']);
        } finally {
            Profiler::stop($span);
        }
    }

    private function applyToCurrentQuote(string $couponCode): void
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getId()) {
            return;
        }
        $quote->setCouponCode($couponCode);
        $quote->collectTotals();
        $this->cartRepo->save($quote);
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
