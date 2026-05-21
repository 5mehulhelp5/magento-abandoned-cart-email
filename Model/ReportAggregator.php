<?php
/**
 * Etechflow_AbandonedCart - Recovery dashboard aggregator.
 *
 * Pulls totals + rates from the two tracking tables for the admin
 * Reports page. Single query per metric group — direct connection use
 * is justified here per ETechFlow Module Development Standards §6 (no
 * N+1, summary work belongs in SQL not PHP).
 *
 * All times are normalized to UTC because that's what timestamps are
 * stored as in the DB.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class ReportAggregator
{
    private const TABLE_CART = 'etechflow_abandoned_cart';
    private const TABLE_LOG  = 'etechflow_abandoned_cart_email_log';
    private const TABLE_RULE = 'etechflow_abandoned_cart_rule';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Return high-level KPI numbers for the given window.
     *
     * @return array<string, int|float>
     */
    public function getSummary(string $fromDate, string $toDate, ?int $storeId = null): array
    {
        try {
            $conn = $this->resource->getConnection();
            $cartTable = $this->resource->getTableName(self::TABLE_CART);
            $logTable  = $this->resource->getTableName(self::TABLE_LOG);

            $cartSelect = $conn->select()
                ->from(
                    ['c' => $cartTable],
                    [
                        'total_abandoned'   => 'COUNT(*)',
                        'total_recovered'   => 'SUM(CASE WHEN c.status = ' . AbandonedCartInterface::STATUS_RECOVERED . ' THEN 1 ELSE 0 END)',
                        'revenue_recovered' => 'COALESCE(SUM(c.recovered_revenue), 0)',
                    ]
                )
                ->where('c.abandoned_at >= ?', $fromDate)
                ->where('c.abandoned_at <= ?', $toDate);

            if ($storeId !== null) {
                $cartSelect->where('c.store_id = ?', $storeId);
            }
            $cartRow = $conn->fetchRow($cartSelect) ?: [];

            $logSelect = $conn->select()
                ->from(
                    ['l' => $logTable],
                    [
                        'emails_sent'      => 'SUM(CASE WHEN l.status IN (' . implode(',', [
                            EmailLogInterface::STATUS_SENT,
                            EmailLogInterface::STATUS_OPENED,
                            EmailLogInterface::STATUS_CLICKED,
                            EmailLogInterface::STATUS_CONVERTED,
                        ]) . ') THEN 1 ELSE 0 END)',
                        'emails_opened'    => 'SUM(CASE WHEN l.opened_at IS NOT NULL THEN 1 ELSE 0 END)',
                        'emails_clicked'   => 'SUM(CASE WHEN l.clicked_at IS NOT NULL THEN 1 ELSE 0 END)',
                        'emails_converted' => 'SUM(CASE WHEN l.status = ' . EmailLogInterface::STATUS_CONVERTED . ' THEN 1 ELSE 0 END)',
                        'emails_failed'    => 'SUM(CASE WHEN l.status = ' . EmailLogInterface::STATUS_FAILED . ' THEN 1 ELSE 0 END)',
                    ]
                )
                ->where('l.created_at >= ?', $fromDate)
                ->where('l.created_at <= ?', $toDate);

            if ($storeId !== null) {
                $logSelect->joinInner(['c' => $cartTable], 'l.cart_id = c.entity_id', []);
                $logSelect->where('c.store_id = ?', $storeId);
            }
            $logRow = $conn->fetchRow($logSelect) ?: [];

            $totalAbandoned = (int) ($cartRow['total_abandoned'] ?? 0);
            $totalRecovered = (int) ($cartRow['total_recovered'] ?? 0);
            $emailsSent     = (int) ($logRow['emails_sent'] ?? 0);
            $emailsOpened   = (int) ($logRow['emails_opened'] ?? 0);
            $emailsClicked  = (int) ($logRow['emails_clicked'] ?? 0);

            return [
                'total_abandoned'   => $totalAbandoned,
                'total_recovered'   => $totalRecovered,
                'recovery_rate'     => $this->rate($totalRecovered, $totalAbandoned),
                'revenue_recovered' => (float) ($cartRow['revenue_recovered'] ?? 0),
                'emails_sent'       => $emailsSent,
                'emails_opened'     => $emailsOpened,
                'open_rate'         => $this->rate($emailsOpened, $emailsSent),
                'emails_clicked'    => $emailsClicked,
                'click_rate'        => $this->rate($emailsClicked, $emailsSent),
                'emails_converted'  => (int) ($logRow['emails_converted'] ?? 0),
                'emails_failed'     => (int) ($logRow['emails_failed'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: ReportAggregator.getSummary failed',
                ['exception' => $e->getMessage()]
            );
            return $this->emptySummary();
        }
    }

    /**
     * Per-rule breakdown — which rule recovers most carts?
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPerRuleSummary(string $fromDate, string $toDate, ?int $storeId = null): array
    {
        try {
            $conn      = $this->resource->getConnection();
            $cartTable = $this->resource->getTableName(self::TABLE_CART);
            $logTable  = $this->resource->getTableName(self::TABLE_LOG);
            $ruleTable = $this->resource->getTableName(self::TABLE_RULE);

            $select = $conn->select()
                ->from(['r' => $ruleTable], [
                    'rule_id'         => 'r.rule_id',
                    'rule_name'       => 'r.name',
                    'is_active'       => 'r.is_active',
                    'sequence_number' => 'r.sequence_number',
                ])
                ->joinLeft(
                    ['l' => $logTable],
                    'l.rule_id = r.rule_id AND l.created_at >= ' . $conn->quote($fromDate)
                    . ' AND l.created_at <= ' . $conn->quote($toDate),
                    [
                        'sent'      => 'SUM(CASE WHEN l.status IN (' . implode(',', [
                            EmailLogInterface::STATUS_SENT,
                            EmailLogInterface::STATUS_OPENED,
                            EmailLogInterface::STATUS_CLICKED,
                            EmailLogInterface::STATUS_CONVERTED,
                        ]) . ') THEN 1 ELSE 0 END)',
                        'opened'    => 'SUM(CASE WHEN l.opened_at IS NOT NULL THEN 1 ELSE 0 END)',
                        'clicked'   => 'SUM(CASE WHEN l.clicked_at IS NOT NULL THEN 1 ELSE 0 END)',
                        'converted' => 'SUM(CASE WHEN l.status = ' . EmailLogInterface::STATUS_CONVERTED . ' THEN 1 ELSE 0 END)',
                    ]
                )
                ->group(['r.rule_id', 'r.name', 'r.is_active', 'r.sequence_number'])
                ->order('r.priority ASC');

            if ($storeId !== null) {
                $select->joinLeft(['c' => $cartTable], 'l.cart_id = c.entity_id', []);
                $select->where('c.store_id = ? OR c.store_id IS NULL', $storeId);
            }

            $rows = $conn->fetchAll($select) ?: [];
            foreach ($rows as &$row) {
                $sent = (int) ($row['sent'] ?? 0);
                $row['sent']             = $sent;
                $row['opened']           = (int) ($row['opened'] ?? 0);
                $row['clicked']          = (int) ($row['clicked'] ?? 0);
                $row['converted']        = (int) ($row['converted'] ?? 0);
                $row['open_rate']        = $this->rate($row['opened'], $sent);
                $row['click_rate']       = $this->rate($row['clicked'], $sent);
                $row['conversion_rate']  = $this->rate($row['converted'], $sent);
            }
            return $rows;
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: ReportAggregator.getPerRuleSummary failed',
                ['exception' => $e->getMessage()]
            );
            return [];
        }
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }
        return round(($numerator / $denominator) * 100, 2);
    }

    /**
     * @return array<string, int|float>
     */
    private function emptySummary(): array
    {
        return [
            'total_abandoned'   => 0,
            'total_recovered'   => 0,
            'recovery_rate'     => 0.0,
            'revenue_recovered' => 0.0,
            'emails_sent'       => 0,
            'emails_opened'     => 0,
            'open_rate'         => 0.0,
            'emails_clicked'    => 0,
            'click_rate'        => 0.0,
            'emails_converted'  => 0,
            'emails_failed'     => 0,
        ];
    }
}
