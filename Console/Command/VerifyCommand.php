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
use Etechflow\AbandonedCart\Api\Data\PopupImpressionInterface;
use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Etechflow\AbandonedCart\Api\EmailLogRepositoryInterface;
use Etechflow\AbandonedCart\Api\PopupImpressionRepositoryInterface;
use Etechflow\AbandonedCart\Api\PopupRuleRepositoryInterface;
use Etechflow\AbandonedCart\Api\RuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\AbandonedCartFactory;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\EmailLogFactory;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Etechflow\AbandonedCart\Model\PopupImpressionFactory;
use Etechflow\AbandonedCart\Model\PopupRuleFactory;
use Etechflow\AbandonedCart\Model\Source\CartStatus;
use Etechflow\AbandonedCart\Model\Source\EmailLogStatus;
use Etechflow\AbandonedCart\Model\Source\PopupDeviceType;
use Etechflow\AbandonedCart\Model\Source\PopupFrequency;
use Etechflow\AbandonedCart\Model\Source\PopupPageScope;
use Etechflow\AbandonedCart\Model\Source\PopupTriggerType;
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
        private readonly PopupRuleRepositoryInterface $popupRuleRepo,
        private readonly PopupImpressionRepositoryInterface $popupImpressionRepo,
        private readonly PopupRuleFactory $popupRuleFactory,
        private readonly PopupImpressionFactory $popupImpressionFactory,
        private readonly PopupTriggerType $popupTriggerSource,
        private readonly PopupPageScope $popupPageScopeSource,
        private readonly PopupFrequency $popupFrequencySource,
        private readonly PopupDeviceType $popupDeviceSource,
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
        $cleanupCartId       = null;
        $cleanupLogId        = null;
        $cleanupPopupRuleId  = null;
        $cleanupImpressionId = null;

        try {
            $this->runStep($output, 1, 'Module enabled', function (): void {
                if (!$this->moduleManager->isEnabled(self::MODULE_NAME)) {
                    throw new \RuntimeException('Module is not enabled in Magento.');
                }
            }, $passed, $failed);

            $this->runStep($output, 2, 'DB tables present (5)', function (): void {
                $conn = $this->resource->getConnection();
                foreach ([
                    'etechflow_abandoned_cart',
                    'etechflow_abandoned_cart_rule',
                    'etechflow_abandoned_cart_email_log',
                    'etechflow_popup_rule',
                    'etechflow_popup_impression',
                ] as $t) {
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

            $this->runStep($output, 10, 'PopupRuleRepository lists rules', function (): void {
                $rules = $this->popupRuleRepo->getAll();
                if (!is_array($rules)) {
                    throw new \RuntimeException('PopupRule getAll() did not return array.');
                }
            }, $passed, $failed);

            $this->runStep($output, 11, 'PopupImpressionRepository lists impressions', function (): void {
                $imprs = $this->popupImpressionRepo->getAll();
                if (!is_array($imprs)) {
                    throw new \RuntimeException('PopupImpression getAll() did not return array.');
                }
            }, $passed, $failed);

            $this->runStep($output, 12, 'Popup source models return options', function (): void {
                if (count($this->popupTriggerSource->toOptionArray()) !== 4) {
                    throw new \RuntimeException('PopupTriggerType expected 4 options.');
                }
                if (count($this->popupPageScopeSource->toOptionArray()) !== 5) {
                    throw new \RuntimeException('PopupPageScope expected 5 options.');
                }
                if (count($this->popupFrequencySource->toOptionArray()) !== 3) {
                    throw new \RuntimeException('PopupFrequency expected 3 options.');
                }
                if (count($this->popupDeviceSource->toOptionArray()) !== 3) {
                    throw new \RuntimeException('PopupDeviceType expected 3 options.');
                }
            }, $passed, $failed);

            $this->runStep($output, 13, 'PopupRule repo round-trip', function () use (&$cleanupPopupRuleId): void {
                $rule = $this->popupRuleFactory->create();
                $rule->setName('verifier-popup');
                $rule->setIsActive(false);
                $rule->setPriority(99);
                $rule->setTriggerType(PopupRuleInterface::TRIGGER_EXIT_INTENT);
                $rule->setTriggerValue(0);
                $rule->setPageScope(PopupRuleInterface::SCOPE_ALL);
                $rule->setStoreIds('0');
                $rule->setCustomerGroupIds('0');
                $rule->setPopupHeadline('verifier headline');
                $rule->setPopupCtaText('OK');
                $rule->setApplyToGuests(true);
                $rule->setFrequency(PopupRuleInterface::FREQUENCY_ONCE_PER_SESSION);
                $rule->setMaxImpressionsPerCustomer(1);
                $this->popupRuleRepo->save($rule);
                $cleanupPopupRuleId = (int) $rule->getRuleId();

                $loaded = $this->popupRuleRepo->getById($cleanupPopupRuleId);
                if ($loaded->getName() !== 'verifier-popup') {
                    throw new \RuntimeException('PopupRule round-trip data mismatch.');
                }
            }, $passed, $failed);

            $this->runStep($output, 14, 'PopupImpression repo round-trip', function () use ($cleanupPopupRuleId, &$cleanupImpressionId): void {
                if ($cleanupPopupRuleId === null) {
                    throw new \RuntimeException('Step 13 prerequisite missing.');
                }
                $impr = $this->popupImpressionFactory->create();
                $impr->setPopupRuleId($cleanupPopupRuleId);
                $impr->setSessionId('verifier-session-' . bin2hex(random_bytes(8)));
                $impr->setStoreId(1);
                $impr->setDeviceType(PopupImpressionInterface::DEVICE_DESKTOP);
                $impr->setShownAt(date('Y-m-d H:i:s'));
                $this->popupImpressionRepo->save($impr);
                $cleanupImpressionId = (int) $impr->getImpressionId();

                $loaded = $this->popupImpressionRepo->getById($cleanupImpressionId);
                if ($loaded->getPopupRuleId() !== $cleanupPopupRuleId) {
                    throw new \RuntimeException('PopupImpression round-trip mismatch.');
                }
            }, $passed, $failed);
        } finally {
            $this->cleanup($cleanupLogId, $cleanupCartId, $cleanupImpressionId, $cleanupPopupRuleId, $output);
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

    private function cleanup(
        ?int $logId,
        ?int $cartId,
        ?int $impressionId,
        ?int $popupRuleId,
        OutputInterface $output
    ): void {
        try {
            if ($logId !== null) {
                $this->emailLogRepo->deleteById($logId);
            }
            if ($cartId !== null) {
                $this->cartRepo->deleteById($cartId);
            }
            if ($impressionId !== null) {
                $this->popupImpressionRepo->deleteById($impressionId);
            }
            if ($popupRuleId !== null) {
                $this->popupRuleRepo->deleteById($popupRuleId);
            }
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<comment>Cleanup warning: %s</comment>', $e->getMessage()));
        }
    }
}
