<?php
/**
 * Etechflow_AbandonedCart - End-to-end smoke verifier.
 *
 * `bin/magento etechflow:abc:verify`
 *
 * Per ETechFlow Module Development Standards §8: idempotent + self-cleaning
 * + step-by-step OK/FAIL output + exit 0 on full pass, 1 on any failure.
 *
 * Steps:
 *   1. Module is enabled in Magento
 *   2. DB tables present (3)
 *   3. Config wrapper readable + returns sane defaults
 *   4. LicenseValidator works (dev-host bypass or valid key)
 *   5. RuleRepository lists rules
 *   6. Source models return options
 *   7. AbandonedCartRepository round-trip (insert → load → delete)
 *   8. EmailLogRepository round-trip (insert → load → delete)
 *   9. Profiler::start returns null (or span) without throwing
 *
 * Run before/after every deploy. Pipe its output to a file in CI for diff.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Console\Command;

use Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Api\EmailLogRepositoryInterface;
use Etechflow\AbandonedCart\Api\RuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\AbandonedCartFactory;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\EmailLogFactory;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Etechflow\AbandonedCart\Model\Source\CartStatus;
use Etechflow\AbandonedCart\Model\Source\EmailLogStatus;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\Manager as ModuleManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VerifyCommand extends Command
{
    private const MODULE_NAME = 'Etechflow_AbandonedCart';

    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly ResourceConnection $resource,
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly RuleRepositoryInterface $ruleRepo,
        private readonly AbandonedCartRepositoryInterface $cartRepo,
        private readonly EmailLogRepositoryInterface $emailLogRepo,
        private readonly AbandonedCartFactory $cartFactory,
        private readonly EmailLogFactory $emailLogFactory,
        private readonly CartStatus $cartStatusSource,
        private readonly EmailLogStatus $emailLogStatusSource,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('etechflow:abc:verify');
        $this->setDescription('End-to-end smoke test for Etechflow_AbandonedCart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Etechflow_AbandonedCart end-to-end verifier</info>');
        $output->writeln('');

        $passed = 0;
        $failed = 0;
        $cleanupCartId = null;
        $cleanupLogId  = null;

        try {
            $this->runStep($output, 1, 'Module enabled', function (): void {
                if (!$this->moduleManager->isEnabled(self::MODULE_NAME)) {
                    throw new \RuntimeException('Module is not enabled in Magento.');
                }
            }, $passed, $failed);

            $this->runStep($output, 2, 'DB tables present (3)', function (): void {
                $conn = $this->resource->getConnection();
                foreach (['etechflow_abandoned_cart', 'etechflow_abandoned_cart_rule', 'etechflow_abandoned_cart_email_log'] as $t) {
                    if (!$conn->isTableExists($this->resource->getTableName($t))) {
                        throw new \RuntimeException(sprintf('Table %s missing.', $t));
                    }
                }
            }, $passed, $failed);

            $this->runStep($output, 3, 'Config wrapper readable', function (): void {
                $threshold = $this->config->getAbandonmentThresholdMinutes();
                if ($threshold <= 0) {
                    throw new \RuntimeException('Threshold misconfigured: ' . $threshold);
                }
            }, $passed, $failed);

            $this->runStep($output, 4, 'LicenseValidator works', function (): void {
                $valid = $this->licenseValidator->isValid();
                if (!is_bool($valid)) {
                    throw new \RuntimeException('LicenseValidator did not return bool.');
                }
            }, $passed, $failed);

            $this->runStep($output, 5, 'RuleRepository lists rules', function (): void {
                $rules = $this->ruleRepo->getAll();
                if (!is_array($rules)) {
                    throw new \RuntimeException('getAll() did not return array.');
                }
            }, $passed, $failed);

            $this->runStep($output, 6, 'Source models return options', function (): void {
                if (count($this->cartStatusSource->toOptionArray()) !== 5) {
                    throw new \RuntimeException('CartStatus expected 5 options.');
                }
                if (count($this->emailLogStatusSource->toOptionArray()) !== 6) {
                    throw new \RuntimeException('EmailLogStatus expected 6 options.');
                }
            }, $passed, $failed);

            $this->runStep($output, 7, 'AbandonedCart repo round-trip', function () use (&$cleanupCartId): void {
                $cart = $this->cartFactory->create();
                $cart->setQuoteId(999999999);
                $cart->setStoreId(1);
                $cart->setCustomerEmail('verifier@etechflow.local');
                $cart->setCustomerGroupId(0);
                $cart->setItemsCount(1);
                $cart->setItemsQty(1);
                $cart->setSubtotal(0.01);
                $cart->setGrandTotal(0.01);
                $cart->setCurrencyCode('USD');
                $cart->setStatus(AbandonedCartInterface::STATUS_PENDING);
                $cart->setRestoreToken(bin2hex(random_bytes(32)));
                $cart->setEmailsSent(0);
                $cart->setAbandonedAt(date('Y-m-d H:i:s'));
                $this->cartRepo->save($cart);
                $cleanupCartId = (int) $cart->getEntityId();

                $loaded = $this->cartRepo->getById($cleanupCartId);
                if ($loaded->getCustomerEmail() !== 'verifier@etechflow.local') {
                    throw new \RuntimeException('Round-trip data mismatch.');
                }
            }, $passed, $failed);

            $this->runStep($output, 8, 'EmailLog repo round-trip', function () use ($cleanupCartId, &$cleanupLogId): void {
                if ($cleanupCartId === null) {
                    throw new \RuntimeException('Step 7 prerequisite missing.');
                }
                $log = $this->emailLogFactory->create();
                $log->setCartId($cleanupCartId);
                $log->setRecipientEmail('verifier@etechflow.local');
                $log->setEmailTemplate('etechflow_abandoned_cart_default_template');
                $log->setSequenceNumber(1);
                $log->setStatus(EmailLogInterface::STATUS_QUEUED);
                $log->setOpenCount(0);
                $log->setClickCount(0);
                $log->setCreatedAt(date('Y-m-d H:i:s'));
                $this->emailLogRepo->save($log);
                $cleanupLogId = (int) $log->getLogId();

                $loaded = $this->emailLogRepo->getById($cleanupLogId);
                if ($loaded->getRecipientEmail() !== 'verifier@etechflow.local') {
                    throw new \RuntimeException('EmailLog round-trip mismatch.');
                }
            }, $passed, $failed);

            $this->runStep($output, 9, 'Profiler no-op safe', function (): void {
                $span = Profiler::start('Etechflow_ABC_VerifySmokeTest');
                Profiler::stop($span);
            }, $passed, $failed);
        } finally {
            $this->cleanup($cleanupLogId, $cleanupCartId, $output);
        }

        $output->writeln('');
        $total = $passed + $failed;
        if ($failed === 0) {
            $output->writeln(sprintf('<info>ALL CHECKS PASSED (%d/%d)</info>', $passed, $total));
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<error>%d / %d CHECKS FAILED</error>', $failed, $total));
        return Command::FAILURE;
    }

    private function runStep(OutputInterface $output, int $step, string $label, callable $check, int &$passed, int &$failed): void
    {
        try {
            $check();
            $output->writeln(sprintf('  <info>%2d. OK</info>   %s', $step, $label));
            $passed++;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('  <error>%2d. FAIL</error> %s — %s', $step, $label, $e->getMessage()));
            $failed++;
        }
    }

    private function cleanup(?int $logId, ?int $cartId, OutputInterface $output): void
    {
        try {
            if ($logId !== null) {
                $this->emailLogRepo->deleteById($logId);
            }
            if ($cartId !== null) {
                $this->cartRepo->deleteById($cartId);
            }
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<comment>Cleanup warning: %s</comment>', $e->getMessage()));
        }
    }
}
