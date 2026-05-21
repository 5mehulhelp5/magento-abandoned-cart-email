<?php
/**
 * Etechflow_AbandonedCart - Shared recovery-attribution service.
 *
 * Centralizes the "an order was placed, so the matching abandoned-cart row
 * is now RECOVERED" logic so [[Etechflow\AbandonedCart\Observer\OrderPlaceAfterObserver]]
 * and [[Etechflow\AbandonedCart\Plugin\Quote\SubmitPlugin]] both delegate
 * here instead of duplicating the work.
 *
 * Idempotent: a second invocation against the same already-recovered cart
 * returns early — supports the belt-and-suspenders Observer+Plugin combo
 * without double-counting revenue.
 *
 * Silent on failure per §19 — recovery attribution NEVER breaks order
 * placement. If our DB update throws, we log and swallow.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Api\EmailLogRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class RecoveryService
{
    public function __construct(
        private readonly AbandonedCartRepositoryInterface $cartRepo,
        private readonly EmailLogRepositoryInterface $emailLogRepo,
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function markRecovered(int $quoteId, int $orderId, float $orderTotal): void
    {
        if (!$this->config->isEnabled() || !$this->licenseValidator->isValid()) {
            return;
        }

        if ($quoteId <= 0 || $orderId <= 0) {
            return;
        }

        try {
            $cart = $this->cartRepo->getByQuoteId($quoteId);
            if ($cart === null) {
                return;
            }

            if ($cart->getStatus() === AbandonedCartInterface::STATUS_RECOVERED) {
                return;
            }

            $now = $this->dateTime->gmtDate();

            $cart->setStatus(AbandonedCartInterface::STATUS_RECOVERED);
            $cart->setRecoveredOrderId($orderId);
            $cart->setRecoveredRevenue($orderTotal);
            $cart->setRecoveredAt($now);
            $this->cartRepo->save($cart);

            $this->attributeConversionToLatestEngagedEmail((int) $cart->getEntityId(), $orderId);

            $this->logger->info(
                'Etechflow_AbandonedCart: cart recovered',
                [
                    'cart_id'  => $cart->getEntityId(),
                    'quote_id' => $quoteId,
                    'order_id' => $orderId,
                    'revenue'  => $orderTotal,
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: recovery attribution failed',
                [
                    'quote_id'  => $quoteId,
                    'order_id'  => $orderId,
                    'exception' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Walk the email log for this cart, newest sent first, and mark the most
     * recently engaged-with email (SENT / OPENED / CLICKED) as CONVERTED.
     * This is what drives the per-rule recovery rate on the admin Reports
     * dashboard (Phase 18).
     */
    private function attributeConversionToLatestEngagedEmail(int $cartId, int $orderId): void
    {
        $logs = $this->emailLogRepo->getByCartId($cartId);
        if (empty($logs)) {
            return;
        }

        $engagedStatuses = [
            EmailLogInterface::STATUS_SENT,
            EmailLogInterface::STATUS_OPENED,
            EmailLogInterface::STATUS_CLICKED,
        ];

        foreach (array_reverse($logs) as $log) {
            if (in_array($log->getStatus(), $engagedStatuses, true)) {
                $log->setStatus(EmailLogInterface::STATUS_CONVERTED);
                $log->setRecoveredOrderId($orderId);
                $this->emailLogRepo->save($log);
                return;
            }
        }
    }
}
