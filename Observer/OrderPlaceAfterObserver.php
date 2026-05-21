<?php
/**
 * Etechflow_AbandonedCart - OrderPlaceAfterObserver.
 *
 * Listens to `sales_order_place_after` and delegates to
 * [[Etechflow\AbandonedCart\Model\RecoveryService]]. Primary recovery
 * attribution path for the 99% of orders placed through the standard
 * checkout — [[Etechflow\AbandonedCart\Plugin\Quote\SubmitPlugin]] is the
 * belt-and-suspenders backup for edge cases (REST API, admin order
 * creation, third-party checkout extensions that may bypass the event
 * pipeline).
 *
 * Both paths converge on RecoveryService::markRecovered() which is
 * idempotent — firing twice on the same order is a no-op.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Observer;

use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Etechflow\AbandonedCart\Model\RecoveryService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class OrderPlaceAfterObserver implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly RecoveryService $recoveryService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled() || !$this->licenseValidator->isValid()) {
            return;
        }

        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof Order) {
            return;
        }

        $quoteId = (int) $order->getQuoteId();
        if ($quoteId <= 0) {
            return;
        }

        $span = Profiler::start('Etechflow_ABC_RecoveryAttribution');
        try {
            $this->recoveryService->markRecovered(
                $quoteId,
                (int) $order->getId(),
                (float) $order->getGrandTotal()
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: OrderPlaceAfterObserver failed',
                [
                    'order_id'  => $order->getId(),
                    'quote_id'  => $quoteId,
                    'exception' => $e->getMessage(),
                ]
            );
        } finally {
            Profiler::stop($span);
        }
    }
}
