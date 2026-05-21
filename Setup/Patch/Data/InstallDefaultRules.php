<?php
/**
 * Etechflow_AbandonedCart - InstallDefaultRules data patch.
 *
 * Ships three reasonable default rules (1h / 24h / 72h reminders) so a
 * fresh-installed merchant has a starting template instead of an empty
 * Rules grid. All three are DISABLED by default — per ETechFlow Module
 * Development Standards §0 rule 2, no behavioural change happens until
 * the merchant explicitly opts in by toggling each rule active.
 *
 * Why direct connection->insert() and not the repository:
 *   Data patches execute during setup:upgrade BEFORE DI's full graph is
 *   ready. ModuleDataSetupInterface gives us the raw, already-initialized
 *   DB connection, which is the canonical pattern for patches per §12.
 *
 * Idempotency: bail early if ANY row with our default-rule naming
 *   pattern is already in the table. Standards §12 mandates re-running
 *   the patch must be a no-op.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Psr\Log\LoggerInterface;

class InstallDefaultRules implements DataPatchInterface, PatchRevertableInterface
{
    private const TABLE = 'etechflow_abandoned_cart_rule';

    private const DEFAULT_NAME_PATTERN = 'Default: % Reminder %';

    /**
     * Default rules shipped at install time.
     *
     * Tuple structure:
     *   [name, description, send_after_minutes, sequence_number, priority]
     *
     * Descriptions are plain-English per §18 — merchants read them in the
     * Rules grid before deciding whether to activate.
     */
    private const DEFAULT_RULES = [
        [
            'name'               => 'Default: First Reminder (1 hour)',
            'description'        => 'A friendly first reminder sent one hour after a cart is abandoned. '
                                  . 'Most recovery happens here — the customer was genuinely interested '
                                  . 'and got distracted. Disabled by default; review the email template '
                                  . 'and enable when ready to go live.',
            'send_after_minutes' => 60,
            'sequence_number'    => 1,
            'priority'           => 10,
        ],
        [
            'name'               => 'Default: Second Reminder (24 hours)',
            'description'        => 'A second reminder one day later. Often paired with an optional '
                                  . 'discount coupon to nudge fence-sitters. Disabled by default.',
            'send_after_minutes' => 1440,
            'sequence_number'    => 2,
            'priority'           => 20,
        ],
        [
            'name'               => 'Default: Third Reminder (72 hours)',
            'description'        => 'A final reminder three days after abandonment, framed as a '
                                  . '"last chance" message. Disabled by default — past 72 hours the '
                                  . 'conversion rate drops sharply and excess emails risk spam '
                                  . 'complaints.',
            'send_after_minutes' => 4320,
            'sequence_number'    => 3,
            'priority'           => 30,
        ],
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();

        try {
            $connection = $this->moduleDataSetup->getConnection();
            $table = $this->moduleDataSetup->getTable(self::TABLE);

            $existing = $connection->fetchOne(
                $connection->select()
                    ->from($table, ['rule_id'])
                    ->where('name LIKE ?', self::DEFAULT_NAME_PATTERN)
                    ->limit(1)
            );

            if ($existing !== false) {
                $this->logger->info(
                    'Etechflow_AbandonedCart: InstallDefaultRules skipped — defaults already present'
                );
                $this->moduleDataSetup->endSetup();
                return;
            }

            foreach (self::DEFAULT_RULES as $rule) {
                $connection->insert($table, [
                    'name'               => $rule['name'],
                    'description'        => $rule['description'],
                    'is_active'          => 0,
                    'store_ids'          => '0',
                    'customer_group_ids' => '0',
                    'send_after_minutes' => $rule['send_after_minutes'],
                    'sequence_number'    => $rule['sequence_number'],
                    'email_template'     => 'etechflow_abandoned_cart_default_template',
                    'email_sender'       => 'general',
                    'min_cart_subtotal'  => null,
                    'max_cart_subtotal'  => null,
                    'apply_to_guests'    => 1,
                    'priority'           => $rule['priority'],
                ]);
            }

            $this->logger->info(
                'Etechflow_AbandonedCart: InstallDefaultRules inserted ' . count(self::DEFAULT_RULES) . ' default rules'
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: InstallDefaultRules apply() failed',
                ['exception' => $e->getMessage()]
            );
            $this->moduleDataSetup->endSetup();
            throw $e;
        }

        $this->moduleDataSetup->endSetup();
    }

    public function revert(): void
    {
        $this->moduleDataSetup->startSetup();

        try {
            $connection = $this->moduleDataSetup->getConnection();
            $table = $this->moduleDataSetup->getTable(self::TABLE);

            $deleted = $connection->delete(
                $table,
                ['name LIKE ?' => self::DEFAULT_NAME_PATTERN]
            );

            $this->logger->info(
                'Etechflow_AbandonedCart: InstallDefaultRules revert removed ' . $deleted . ' default rules'
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: InstallDefaultRules revert() failed',
                ['exception' => $e->getMessage()]
            );
            $this->moduleDataSetup->endSetup();
            throw $e;
        }

        $this->moduleDataSetup->endSetup();
    }
}
