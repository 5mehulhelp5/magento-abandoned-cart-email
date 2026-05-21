<?php
/**
 * Etechflow_AbandonedCart - AbandonedCart grid collection.
 *
 * UI Component data provider expects a collection that implements
 * SearchResultInterface (so UI Component can plug aggregations + total
 * count + items into the standard listing toolbar). We compose that
 * adapter on top of our regular AbandonedCart\Collection.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel\AbandonedCart\Grid;

use Etechflow\AbandonedCart\Model\ResourceModel\AbandonedCart\Collection as AbandonedCartCollection;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;

class Collection extends AbandonedCartCollection implements SearchResultInterface
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

    /**
     * @param \Magento\Framework\Api\Search\DocumentInterface[] $items
     */
    public function setItems(array $items = null): self
    {
        return $this;
    }
}
