<?php
/**
 * Etechflow_AbandonedCart - PopupRule grid collection.
 *
 * Mirror of [[Etechflow\AbandonedCart\Model\ResourceModel\Rule\Grid\Collection]]
 * for the popup-rule entity. UI Component data provider needs SearchResultInterface.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel\PopupRule\Grid;

use Etechflow\AbandonedCart\Model\ResourceModel\PopupRule\Collection as PopupRuleCollection;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Search\Response\Aggregation;

class Collection extends PopupRuleCollection implements SearchResultInterface
{
    /**
     * @var AggregationInterface|null
     */
    protected $aggregations;

    public function getAggregations(): AggregationInterface
    {
        if ($this->aggregations === null) {
            $this->aggregations = new Aggregation([]);
        }
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

    public function setSearchCriteria(?\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria = null): self
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

    public function setItems(?array $items = null): self
    {
        return $this;
    }
}
