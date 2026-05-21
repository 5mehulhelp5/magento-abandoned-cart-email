<?php
/**
 * Etechflow_AbandonedCart - Rule grid collection.
 *
 * Mirror of [[Etechflow\AbandonedCart\Model\ResourceModel\AbandonedCart\Grid\Collection]]
 * for the rule entity. UI Component data provider needs SearchResultInterface.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel\Rule\Grid;

use Etechflow\AbandonedCart\Model\ResourceModel\Rule\Collection as RuleCollection;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;

class Collection extends RuleCollection implements SearchResultInterface
{
    /**
     * @var AggregationInterface
     */
    protected $aggregations;

    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    public function setAggregations($aggregations): self
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    public function getSearchCriteria()
    {
        return null;
    }

    public function setSearchCriteria(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria = null): self
    {
        return $this;
    }

    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    public function setTotalCount($totalCount): self
    {
        return $this;
    }

    public function setItems(array $items = null): self
    {
        return $this;
    }
}
