<?php
/**
 * Etechflow_AbandonedCart - Rule collection.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel\Rule;

use Etechflow\AbandonedCart\Model\Rule as RuleModel;
use Etechflow\AbandonedCart\Model\ResourceModel\Rule as RuleResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'rule_id';

    protected function _construct(): void
    {
        $this->_init(RuleModel::class, RuleResource::class);
    }
}
