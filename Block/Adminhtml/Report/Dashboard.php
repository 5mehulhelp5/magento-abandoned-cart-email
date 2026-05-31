<?php
/**
 * Etechflow_AbandonedCart - Reports dashboard block.
 *
 * Pulls aggregate data from ReportAggregator (called from template via
 * `$block->getSummary()` / `$block->getPerRuleSummary()`). Lazy-evaluates
 * — the queries don't run until the template asks.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block\Adminhtml\Report;

use Etechflow\AbandonedCart\Controller\Adminhtml\Report\Index as ReportAction;
use Etechflow\AbandonedCart\Model\ReportAggregator;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;

class Dashboard extends Template
{
    private ?array $cachedSummary = null;

    /**
     * @var array<int, array>|null
     */
    private ?array $cachedPerRule = null;

    private ?array $cachedPopupSummary = null;

    /**
     * @var array<int, array>|null
     */
    private ?array $cachedPerPopupRule = null;

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly ReportAggregator $aggregator,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, int|float>
     */
    public function getSummary(): array
    {
        if ($this->cachedSummary === null) {
            [$from, $to] = $this->getDateRange();
            $this->cachedSummary = $this->aggregator->getSummary($from, $to);
        }
        return $this->cachedSummary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPerRuleSummary(): array
    {
        if ($this->cachedPerRule === null) {
            [$from, $to] = $this->getDateRange();
            $this->cachedPerRule = $this->aggregator->getPerRuleSummary($from, $to);
        }
        return $this->cachedPerRule;
    }

    /**
     * @return array<string, int|float>
     */
    public function getPopupSummary(): array
    {
        if ($this->cachedPopupSummary === null) {
            [$from, $to] = $this->getDateRange();
            $this->cachedPopupSummary = $this->aggregator->getPopupSummary($from, $to);
        }
        return $this->cachedPopupSummary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPerPopupRuleSummary(): array
    {
        if ($this->cachedPerPopupRule === null) {
            [$from, $to] = $this->getDateRange();
            $this->cachedPerPopupRule = $this->aggregator->getPerPopupRuleSummary($from, $to);
        }
        return $this->cachedPerPopupRule;
    }

    public function getFromDate(): string
    {
        return (string) ($this->getRange()['from_date'] ?? '');
    }

    public function getToDate(): string
    {
        return (string) ($this->getRange()['to_date'] ?? '');
    }

    public function getFilterFormAction(): string
    {
        return $this->getUrl('etechflow_abandonedcart/report/index');
    }

    /**
     * @return string[]
     */
    private function getDateRange(): array
    {
        $range = $this->getRange();
        return [
            (string) ($range['from'] ?? ''),
            (string) ($range['to']   ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getRange(): array
    {
        $data = $this->registry->registry(ReportAction::REGISTRY_DATE_RANGE);
        return is_array($data) ? $data : [];
    }

    public function formatCurrency(float $amount): string
    {
        return number_format($amount, 2);
    }

    public function formatRate(float $rate): string
    {
        return number_format($rate, 1) . '%';
    }
}
