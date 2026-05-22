<?php
/**
 * Etechflow_AbandonedCart - SendQueuedEmails cron.
 *
 * Two-stage send pipeline: SendReminders (Phase 10) writes QUEUED rows;
 * THIS cron picks them up and actually transmits via SMTP. Separation
 * keeps the scanner's lock window short (it doesn't wait for any one
 * email's TCP round-trip) and isolates retry semantics — a stuck mail
 * server stalls THIS cron's queue, not the discovery cron.
 *
 * Runs every 5 minutes on the offset minute so it doesn't collide with
 * SendReminders' tick - when SendReminders is creating fresh QUEUED rows,
 * this cron is processing the previous batch. Exact cron expressions are
 * in etc/crontab.xml.
 *
 * Performance discipline per §6:
 *   - Profiler span around the loop
 *   - batch_size cap prevents over-running the slot (default 50)
 *   - max_runtime_seconds breaks out of the loop gracefully
 *   - Logger reports sent/failed counts per tick for ops visibility
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Cron;

use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\CronLock;
use Etechflow\AbandonedCart\Model\EmailSender;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Etechflow\AbandonedCart\Model\ResourceModel\EmailLog\CollectionFactory;
use Psr\Log\LoggerInterface;

class SendQueuedEmails
{
    private const LOCK_NAME = 'send_queued_emails';

    public function __construct(
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly CronLock $cronLock,
        private readonly EmailSender $emailSender,
        private readonly CollectionFactory $logCollectionFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->licenseValidator->isValid()) {
            return;
        }

        if (!$this->cronLock->tryAcquire(self::LOCK_NAME, $this->config->getCronLockTimeoutMinutes())) {
            $this->logger->info('Etechflow_AbandonedCart: SendQueuedEmails skipped — lock held');
            return;
        }

        $span        = Profiler::start('Etechflow_ABC_SendQueuedEmails');
        $startTime   = time();
        $maxRuntime  = $this->config->getCronMaxRuntimeSeconds();
        $batchSize   = $this->config->getCronBatchSize();
        $sent        = 0;
        $failed      = 0;

        try {
            $queue = $this->getQueuedLogs($batchSize);
            foreach ($queue as $log) {
                if (time() - $startTime >= $maxRuntime) {
                    $this->logger->info(
                        'Etechflow_AbandonedCart: SendQueuedEmails hit max runtime',
                        ['runtime_seconds' => $maxRuntime, 'sent' => $sent, 'failed' => $failed]
                    );
                    break;
                }

                if ($this->emailSender->send($log)) {
                    $sent++;
                } else {
                    $failed++;
                }
            }

            $this->logger->info(
                'Etechflow_AbandonedCart: SendQueuedEmails completed',
                [
                    'sent'             => $sent,
                    'failed'           => $failed,
                    'runtime_seconds'  => time() - $startTime,
                    'queue_size_seen'  => count($queue),
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: SendQueuedEmails crashed',
                [
                    'exception' => $e->getMessage(),
                    'sent'      => $sent,
                    'failed'    => $failed,
                ]
            );
        } finally {
            $this->cronLock->release(self::LOCK_NAME);
            Profiler::stop($span);
        }
    }

    /**
     * @return EmailLogInterface[]
     */
    private function getQueuedLogs(int $limit): array
    {
        $collection = $this->logCollectionFactory->create();
        $collection->addFieldToFilter(EmailLogInterface::STATUS, EmailLogInterface::STATUS_QUEUED);
        $collection->setOrder(EmailLogInterface::CREATED_AT, 'ASC');
        $collection->setPageSize($limit);
        return array_values($collection->getItems());
    }
}
