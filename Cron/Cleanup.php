<?php
/**
 * Etechflow_AbandonedCart - Cleanup cron.
 *
 * Daily housekeeping. Runs at 3am local time. Three tasks, all bounded by
 * admin-configurable retention windows:
 *
 *   1. **Expire stale PENDING carts.** A cart that has been pending for
 *      half the expired_cart_retention window (default 90/2 = 45 days)
 *      gets flipped to EXPIRED. Stops the SendReminders cron from
 *      considering it on every tick forever — once a customer hasn't
 *      converted in that long, more emails become spam, not recovery.
 *   2. **Delete old email_log rows** past log_retention_days (default 180).
 *      Reports keep their data for the window the merchant configured;
 *      anything older gets pruned so the table stays narrow.
 *   3. **Delete EXPIRED carts** past expired_cart_retention_days. The
 *      original `quote` row in Magento's core stays untouched — we only
 *      drop our own tracking row.
 *
 * Direct connection->delete / ->update for efficiency — at scale these are
 * bulk operations affecting thousands of rows, and loading Models for each
 * would be O(n) for no gain.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Cron;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\CronLock;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class Cleanup
{
    private const LOCK_NAME = 'cleanup';

    private const TABLE_CART = 'etechflow_abandoned_cart';
    private const TABLE_LOG  = 'etechflow_abandoned_cart_email_log';

    private const PENDING_EXPIRE_MIN_DAYS = 7;

    public function __construct(
        private readonly Config $config,
        private readonly CronLock $cronLock,
        private readonly ResourceConnection $resource,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        if (!$this->cronLock->tryAcquire(self::LOCK_NAME, $this->config->getCronLockTimeoutMinutes())) {
            $this->logger->info('Etechflow_AbandonedCart: Cleanup skipped — lock held');
            return;
        }

        $span = Profiler::start('Etechflow_ABC_Cleanup');

        try {
            $expired = $this->expireStalePendingCarts();
            $logsDeleted = $this->deleteOldEmailLogs();
            $cartsDeleted = $this->deleteExpiredCarts();

            $this->logger->info(
                'Etechflow_AbandonedCart: Cleanup completed',
                [
                    'pending_expired'  => $expired,
                    'old_logs_deleted' => $logsDeleted,
                    'expired_deleted'  => $cartsDeleted,
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: Cleanup failed',
                ['exception' => $e->getMessage()]
            );
        } finally {
            $this->cronLock->release(self::LOCK_NAME);
            Profiler::stop($span);
        }
    }

    private function expireStalePendingCarts(): int
    {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_CART);

        $cutoffDays = max(
            self::PENDING_EXPIRE_MIN_DAYS,
            (int) ($this->config->getExpiredCartRetentionDays() / 2)
        );
        $cutoff = $this->dateTime->gmtDate(null, time() - ($cutoffDays * 86400));

        return (int) $conn->update(
            $table,
            [
                'status'     => AbandonedCartInterface::STATUS_EXPIRED,
                'updated_at' => $this->dateTime->gmtDate(),
            ],
            [
                'status = ?'        => AbandonedCartInterface::STATUS_PENDING,
                'abandoned_at < ?'  => $cutoff,
            ]
        );
    }

    private function deleteOldEmailLogs(): int
    {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_LOG);

        $cutoff = $this->dateTime->gmtDate(null, time() - ($this->config->getLogRetentionDays() * 86400));

        return (int) $conn->delete(
            $table,
            ['created_at < ?' => $cutoff]
        );
    }

    private function deleteExpiredCarts(): int
    {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_CART);

        $cutoff = $this->dateTime->gmtDate(null, time() - ($this->config->getExpiredCartRetentionDays() * 86400));

        return (int) $conn->delete(
            $table,
            [
                'status = ?'      => AbandonedCartInterface::STATUS_EXPIRED,
                'updated_at < ?'  => $cutoff,
            ]
        );
    }
}
