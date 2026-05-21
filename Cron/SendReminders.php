<?php
/**
 * Etechflow_AbandonedCart - SendReminders cron.
 *
 * Runs every 5 minutes (crontab.xml schedule). On each tick:
 *   1. Acquire the cron lock — no parallel runs.
 *   2. Load every active Rule, ordered by priority ASC.
 *   3. For each rule, find PENDING carts that:
 *        - have been idle longer than rule.send_after_minutes
 *        - haven't already received this rule's sequence-position email
 *        - haven't hit max_emails_per_cart yet
 *        - match the rule's store / customer-group / subtotal conditions
 *   4. For each match, create a QUEUED email_log row + bump the cart's
 *      emails_sent + last_email_sent_at.
 *   5. Honor batch_size + max_runtime_seconds — never starve other crons.
 *   6. Release the lock in a finally{} so a crash still releases.
 *
 * Phase 10 ships the SCAN + QUEUE mechanism only. Phase 12's EmailSender
 * will take QUEUED rows and actually transmit the email (status → SENT
 * or FAILED). The split lets the heavy SMTP work happen in a separate
 * cron/consumer without holding this cron's lock during slow network IO.
 *
 * Performance discipline per §6:
 *   - Single indexed query per rule (composite index in db_schema covers it)
 *   - Profiler span around the whole tick
 *   - Per-rule + per-cart counters in the log message for ops visibility
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Cron;

use Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Api\Data\RuleInterface;
use Etechflow\AbandonedCart\Api\EmailLogRepositoryInterface;
use Etechflow\AbandonedCart\Api\RuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\CronLock;
use Etechflow\AbandonedCart\Model\EmailLogFactory;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Etechflow\AbandonedCart\Model\ResourceModel\AbandonedCart\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class SendReminders
{
    private const LOCK_NAME = 'send_reminders';

    public function __construct(
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly CronLock $cronLock,
        private readonly AbandonedCartRepositoryInterface $cartRepo,
        private readonly RuleRepositoryInterface $ruleRepo,
        private readonly EmailLogRepositoryInterface $emailLogRepo,
        private readonly EmailLogFactory $emailLogFactory,
        private readonly CollectionFactory $cartCollectionFactory,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->licenseValidator->isValid()) {
            return;
        }

        if (!$this->cronLock->tryAcquire(self::LOCK_NAME, $this->config->getCronLockTimeoutMinutes())) {
            $this->logger->info('Etechflow_AbandonedCart: SendReminders skipped — lock held by another run');
            return;
        }

        $span = Profiler::start('Etechflow_ABC_CronTick');
        $startTime = time();
        $maxRuntime = $this->config->getCronMaxRuntimeSeconds();
        $batchSize = $this->config->getCronBatchSize();
        $maxEmailsPerCart = $this->config->getMaxEmailsPerCart();
        $totalProcessed = 0;

        try {
            $rules = $this->ruleRepo->getActiveRules();
            if (empty($rules)) {
                $this->logger->info('Etechflow_AbandonedCart: SendReminders found no active rules — nothing to do');
                return;
            }

            foreach ($rules as $rule) {
                if (time() - $startTime >= $maxRuntime) {
                    $this->logger->info(
                        'Etechflow_AbandonedCart: SendReminders hit max runtime',
                        ['max_runtime_seconds' => $maxRuntime, 'processed' => $totalProcessed]
                    );
                    break;
                }
                if ($totalProcessed >= $batchSize) {
                    break;
                }

                $candidates = $this->findCandidates($rule, $batchSize - $totalProcessed, $maxEmailsPerCart);
                foreach ($candidates as $cart) {
                    $this->queueReminder($cart, $rule);
                    $totalProcessed++;
                }
            }

            $this->logger->info(
                'Etechflow_AbandonedCart: SendReminders completed',
                [
                    'processed'        => $totalProcessed,
                    'runtime_seconds'  => time() - $startTime,
                    'active_rules'     => count($rules),
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: SendReminders failed',
                [
                    'exception' => $e->getMessage(),
                    'processed' => $totalProcessed,
                ]
            );
        } finally {
            $this->cronLock->release(self::LOCK_NAME);
            Profiler::stop($span);
        }
    }

    /**
     * @return AbandonedCartInterface[]
     */
    private function findCandidates(RuleInterface $rule, int $limit, int $maxEmailsPerCart): array
    {
        $cutoff = $this->dateTime->gmtDate(null, time() - ($rule->getSendAfterMinutes() * 60));
        $sequenceCap = min($rule->getSequenceNumber(), $maxEmailsPerCart);

        $collection = $this->cartCollectionFactory->create();
        $collection->addFieldToFilter(AbandonedCartInterface::STATUS, AbandonedCartInterface::STATUS_PENDING);
        $collection->addFieldToFilter(AbandonedCartInterface::ABANDONED_AT, ['lt' => $cutoff]);
        $collection->addFieldToFilter(AbandonedCartInterface::EMAILS_SENT, ['lt' => $sequenceCap]);

        if (!$rule->isApplyToGuests()) {
            $collection->addFieldToFilter(AbandonedCartInterface::CUSTOMER_ID, ['notnull' => true]);
        }

        if ($rule->getMinCartSubtotal() !== null) {
            $collection->addFieldToFilter(
                AbandonedCartInterface::SUBTOTAL,
                ['gteq' => $rule->getMinCartSubtotal()]
            );
        }
        if ($rule->getMaxCartSubtotal() !== null) {
            $collection->addFieldToFilter(
                AbandonedCartInterface::SUBTOTAL,
                ['lteq' => $rule->getMaxCartSubtotal()]
            );
        }

        $storeIds = array_filter(array_map('trim', explode(',', $rule->getStoreIds())));
        if (!in_array('0', $storeIds, true) && !empty($storeIds)) {
            $collection->addFieldToFilter(
                AbandonedCartInterface::STORE_ID,
                ['in' => $storeIds]
            );
        }

        $groupIds = array_filter(array_map('trim', explode(',', $rule->getCustomerGroupIds())));
        if (!in_array('0', $groupIds, true) && !empty($groupIds)) {
            $collection->addFieldToFilter(
                AbandonedCartInterface::CUSTOMER_GROUP_ID,
                ['in' => $groupIds]
            );
        }

        $collection->setPageSize($limit);
        $collection->setOrder(AbandonedCartInterface::ABANDONED_AT, 'ASC');

        return array_values($collection->getItems());
    }

    private function queueReminder(AbandonedCartInterface $cart, RuleInterface $rule): void
    {
        $now = $this->dateTime->gmtDate();

        /** @var EmailLogInterface $log */
        $log = $this->emailLogFactory->create();
        $log->setCartId((int) $cart->getEntityId());
        $log->setRuleId((int) $rule->getRuleId());
        $log->setRecipientEmail($cart->getCustomerEmail());
        $log->setEmailTemplate($rule->getEmailTemplate());
        $log->setSequenceNumber($rule->getSequenceNumber());
        $log->setStatus(EmailLogInterface::STATUS_QUEUED);
        $log->setOpenCount(0);
        $log->setClickCount(0);
        $log->setCreatedAt($now);
        $this->emailLogRepo->save($log);

        $cart->setEmailsSent($cart->getEmailsSent() + 1);
        $cart->setLastEmailSentAt($now);
        $this->cartRepo->save($cart);

        $this->logger->info(
            'Etechflow_AbandonedCart: reminder queued',
            [
                'cart_id'         => $cart->getEntityId(),
                'rule_id'         => $rule->getRuleId(),
                'rule_name'       => $rule->getName(),
                'sequence_number' => $rule->getSequenceNumber(),
                'recipient'       => $cart->getCustomerEmail(),
            ]
        );
    }
}
