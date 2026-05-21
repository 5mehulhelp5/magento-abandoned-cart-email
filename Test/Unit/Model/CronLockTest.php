<?php
/**
 * Etechflow_AbandonedCart - CronLock tests.
 *
 * Uses a real temp directory (sys_get_temp_dir) for filesystem operations
 * — file locks don't lend themselves to vfsStream because of touch()'s
 * mtime semantics. Each test cleans up its own lock files.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Test\Unit\Model;

use Etechflow\AbandonedCart\Model\CronLock;
use Magento\Framework\Filesystem\DirectoryList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CronLockTest extends TestCase
{
    private DirectoryList&MockObject $directoryList;

    private LoggerInterface&MockObject $logger;

    private string $varDir;

    private CronLock $cronLock;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir() . '/etechflow_cronlock_test_' . uniqid();
        @mkdir($this->varDir, 0775, true);

        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->directoryList->method('getPath')->with(DirectoryList::VAR_DIR)->willReturn($this->varDir);

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->cronLock = new CronLock($this->directoryList, $this->logger);
    }

    protected function tearDown(): void
    {
        $lockDir = $this->varDir . '/locks';
        if (is_dir($lockDir)) {
            foreach (glob($lockDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($lockDir);
        }
        @rmdir($this->varDir);
    }

    public function testAcquireSucceedsOnFreshLock(): void
    {
        $this->assertTrue($this->cronLock->tryAcquire('test_lock', 15));
    }

    public function testSecondAcquireFailsWhileFirstHeld(): void
    {
        $this->cronLock->tryAcquire('test_lock', 15);
        $this->assertFalse($this->cronLock->tryAcquire('test_lock', 15));
    }

    public function testReleaseAllowsReacquire(): void
    {
        $this->cronLock->tryAcquire('test_lock', 15);
        $this->cronLock->release('test_lock');
        $this->assertTrue($this->cronLock->tryAcquire('test_lock', 15));
    }

    public function testStaleLockIsAutoRemoved(): void
    {
        $this->cronLock->tryAcquire('test_lock', 15);

        // Forge the lock file's mtime to be 30 minutes ago (older than timeout)
        $lockFile = $this->varDir . '/locks/etechflow_abandoned_cart_test_lock.lock';
        $this->assertFileExists($lockFile);
        touch($lockFile, time() - 1800);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('stale cron lock'));

        $this->assertTrue($this->cronLock->tryAcquire('test_lock', 15));
    }

    public function testIndependentLocksDoNotInterfere(): void
    {
        $this->assertTrue($this->cronLock->tryAcquire('lock_a', 15));
        $this->assertTrue($this->cronLock->tryAcquire('lock_b', 15));
        $this->assertFalse($this->cronLock->tryAcquire('lock_a', 15));
        $this->assertFalse($this->cronLock->tryAcquire('lock_b', 15));
    }

    public function testReleaseOnNonexistentLockIsNoOp(): void
    {
        $this->cronLock->release('never_acquired');
        $this->expectNotToPerformAssertions();
    }
}
