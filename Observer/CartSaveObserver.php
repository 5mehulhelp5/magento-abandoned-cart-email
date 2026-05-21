<?php
/**
 * Etechflow_AbandonedCart - CartSaveObserver.
 *
 * Listens to `sales_quote_save_after` — Magento fires this on every cart
 * mutation (add item, update qty, change customer, etc.). Hot path: this
 * runs hundreds of times per shopping session across all customers.
 *
 * What it does:
 *   For each cart save with items + a customer email, we record (or refresh)
 *   a row in `etechflow_abandoned_cart` so cron can later check whether the
 *   cart has gone idle past the abandonment threshold. We do NOT make the
 *   abandonment decision here — that's the cron's job (Phase 10). All we do
 *   is keep tracking state in sync with the live cart.
 *
 * Mandatory guards per ETechFlow Module Development Standards §11:
 *   1. isEnabled — module + license + master kill-switch
 *   2. _bulk_importer — Magento's bulk-quote-import code path opt-out
 *   3. _indexer_processing — Magento's reindex paths
 *   4. relevant change — only act when the cart is in a state we'd want to
 *      track (has items, has email, status not already terminal)
 *
 * Performance discipline per §6:
 *   - Wrap the body in a Profiler span (no-op when Tideways absent)
 *   - Existing-row check is a single indexed query (quote_id is UNIQUE)
 *   - Insert or update is one statement; no N+1
 *   - try/catch ensures a misconfigured module never breaks cart save
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Observer;

use Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Model\AbandonedCartFactory;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

class CartSaveObserver implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly AbandonedCartRepositoryInterface $cartRepo,
        private readonly AbandonedCartFactory $cartFactory,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled() || !$this->licenseValidator->isValid()) {
            return;
        }

        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        if (!$quote instanceof Quote) {
            return;
        }

        if ($quote->getData('_bulk_importer')) {
            return;
        }

        if ($quote->getData('_indexer_processing')) {
            return;
        }

        if (!$this->isTrackable($quote)) {
            return;
        }

        $span = Profiler::start('Etechflow_ABC_CartSaveTrack');
        try {
            $this->recordCart($quote);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: CartSaveObserver failed',
                [
                    'quote_id'  => $quote->getId(),
                    'exception' => $e->getMessage(),
                ]
            );
        } finally {
            Profiler::stop($span);
        }
    }

    /**
     * Cart is trackable if it has items AND an email to send the reminder to.
     * Pre-checkout anonymous carts without an email cannot be reminded — we
     * simply don't record them and rely on the next save (after email is
     * captured) to start tracking.
     */
    private function isTrackable(Quote $quote): bool
    {
        if ((int) $quote->getId() <= 0) {
            return false;
        }

        if ((int) $quote->getItemsCount() < 1) {
            return false;
        }

        $email = (string) $quote->getCustomerEmail();
        if ($email === '') {
            return false;
        }

        return true;
    }

    private function recordCart(Quote $quote): void
    {
        $existing = $this->cartRepo->getByQuoteId((int) $quote->getId());

        if ($existing !== null) {
            $this->refreshExisting($existing, $quote);
            return;
        }

        $this->createNew($quote);
    }

    private function refreshExisting(AbandonedCartInterface $cart, Quote $quote): void
    {
        if ($this->isTerminalStatus($cart->getStatus())) {
            return;
        }

        $now = $this->dateTime->gmtDate();

        $cart->setItemsCount((int) $quote->getItemsCount());
        $cart->setItemsQty((int) $quote->getItemsQty());
        $cart->setSubtotal((float) $quote->getSubtotal());
        $cart->setGrandTotal((float) $quote->getGrandTotal());
        $cart->setCustomerEmail((string) $quote->getCustomerEmail());
        $cart->setCustomerFirstname((string) $quote->getCustomerFirstname() ?: null);
        $cart->setCustomerLastname((string) $quote->getCustomerLastname() ?: null);
        $cart->setAbandonedAt($now);
        $cart->setStatus(AbandonedCartInterface::STATUS_PENDING);

        $this->cartRepo->save($cart);
    }

    private function createNew(Quote $quote): void
    {
        $now = $this->dateTime->gmtDate();

        /** @var AbandonedCartInterface $cart */
        $cart = $this->cartFactory->create();
        $cart->setQuoteId((int) $quote->getId());
        $cart->setStoreId((int) $quote->getStoreId());
        $cart->setCustomerId($quote->getCustomerId() ? (int) $quote->getCustomerId() : null);
        $cart->setCustomerEmail((string) $quote->getCustomerEmail());
        $cart->setCustomerFirstname((string) $quote->getCustomerFirstname() ?: null);
        $cart->setCustomerLastname((string) $quote->getCustomerLastname() ?: null);
        $cart->setCustomerGroupId((int) $quote->getCustomerGroupId());
        $cart->setItemsCount((int) $quote->getItemsCount());
        $cart->setItemsQty((int) $quote->getItemsQty());
        $cart->setSubtotal((float) $quote->getSubtotal());
        $cart->setGrandTotal((float) $quote->getGrandTotal());
        $cart->setCurrencyCode((string) $quote->getQuoteCurrencyCode());
        $cart->setStatus(AbandonedCartInterface::STATUS_PENDING);
        $cart->setRestoreToken($this->generateRestoreToken());
        $cart->setEmailsSent(0);
        $cart->setAbandonedAt($now);

        $this->cartRepo->save($cart);
    }

    /**
     * A cart in a terminal status (already recovered, expired, or
     * unsubscribed) should not be touched by ongoing cart saves. The
     * customer may keep editing their cart but our tracking row stays
     * frozen at the terminal state until cleanup cron archives it.
     */
    private function isTerminalStatus(int $status): bool
    {
        return in_array(
            $status,
            [
                AbandonedCartInterface::STATUS_RECOVERED,
                AbandonedCartInterface::STATUS_EXPIRED,
                AbandonedCartInterface::STATUS_UNSUBSCRIBED,
            ],
            true
        );
    }

    /**
     * 32 random bytes = 64 hex chars, matches the restore_token column width.
     * Cryptographically secure source; collisions over the lifetime of any
     * realistic install are astronomically unlikely.
     */
    private function generateRestoreToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
