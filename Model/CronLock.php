<?php
/**
 * Etechflow_AbandonedCart - File-based cron lock.
 *
 * Prevents overlapping runs of the same cron job — without a lock, if the
 * 5-minute SendReminders job takes 6 minutes to complete, the next cron
 * tick would start a parallel run that fights it for the same rows. File
 * lock with mtime-based staleness lets crons recover automatically when a
 * previous run crashed mid-execution (no manual lock cleanup needed).
 *
 * Lock files live in var/locks/ inside the Magento root. Magento writes to
 * var/ on every request, so this directory is guaranteed writable.
 *
 * Why a file lock and not a DB lock or Redis lock:
 *   - File locks have zero infrastructure dependencies (works on any
 *     Magento install, with or without Redis).
 *   - The lock is bound to the host's filesystem, which is also where the
 *     cron actually runs. Same blast radius.
 *   - Cleanup-on-crash via mtime check is simpler than DB lock TTL.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Magento\Framework\Filesystem\DirectoryList;
use Psr\Log\LoggerInterface;

class CronLock
{
    private const LOCK_SUBDIR = 'locks';
    private const LOCK_PREFIX = 'etechflow_abandoned_cart_';

    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Attempt to acquire the named lock. Returns true if acquired, false if
     * another process holds it. If an existing lock is older than
     * $timeoutMinutes, treat as stale + take it over (the previous run
     * crashed without releasing).
     */
    public function tryAcquire(string $name, int $timeoutMinutes): bool
    {
        $path = $this->getLockPath($name);

        if (file_exists($path)) {
            $age = time() - (int) @filemtime($path);
            if ($age <= $timeoutMinutes * 60) {
                return false;
            }
            @unlink($path);
            $this->logger->warning(
                'Etechflow_AbandonedCart: stale cron lock auto-removed',
                ['name' => $name, 'age_seconds' => $age]
            );
        }

        return @touch($path);
    }

    public function release(string $name): void
    {
        $path = $this->getLockPath($name);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    private function getLockPath(string $name): string
    {
        $varDir = $this->directoryList->getPath(DirectoryList::VAR_DIR);
        $lockDir = $varDir . DIRECTORY_SEPARATOR . self::LOCK_SUBDIR;
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }
        return $lockDir . DIRECTORY_SEPARATOR . self::LOCK_PREFIX . $name . '.lock';
    }
}
