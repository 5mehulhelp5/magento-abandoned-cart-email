<?php
/**
 * Etechflow_AbandonedCart - Quote\SubmitPlugin.
 *
 * Defensive backup for [[Etechflow\AbandonedCart\Observer\OrderPlaceAfterObserver]].
 *
 * `\Magento\Quote\Model\QuoteManagement::submit()` is the deepest layer of
 * order placement — anything that produces an Order from a Quote goes
 * through here. Some edge paths (REST API order creation, programmatic
 * order generation by extensions, admin "create order from quote" workflows)
 * dispatch `sales_order_place_after` AT THE SAME LEVEL but not always — so
 * we plug submit() as a guarantee.
 *
 * Per §11, this is an after-plugin (lowest-risk plugin type) — we only read
 * the return value, never modify it. Order is returned untouched.
 *
 * RecoveryService::markRecovered() is idempotent — if the observer already
 * fired, the second call here finds status=RECOVERED and returns early.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Plugin\Quote;

use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Etechflow\AbandonedCart\Model\RecoveryService;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

class SubmitPlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly RecoveryService $recoveryService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param QuoteManagement $subject
     * @param OrderInterface $order
     * @return OrderInterface
     */
    public function afterSubmit(QuoteManagement $subject, OrderInterface $order): OrderInterface
    {
        if (!$this->config->isEnabled() || !$this->licenseValidator->isValid()) {
            return $order;
        }

        $quoteId = (int) $order->getQuoteId();
        if ($quoteId <= 0) {
            return $order;
        }

        $span = Profiler::start('Etechflow_ABC_RecoveryAttributionPlugin');
        try {
            $this->recoveryService->markRecovered(
                $quoteId,
                (int) $order->getEntityId(),
                (float) $order->getGrandTotal()
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: SubmitPlugin failed',
                [
                    'order_id'  => $order->getEntityId(),
                    'quote_id'  => $quoteId,
                    'exception' => $e->getMessage(),
                ]
            );
        } finally {
            Profiler::stop($span);
        }

        return $order;
    }
}
