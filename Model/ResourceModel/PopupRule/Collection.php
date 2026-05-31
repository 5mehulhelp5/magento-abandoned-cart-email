<?php
/**
 * Etechflow_AbandonedCart - PopupRule collection.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel\PopupRule;

use Etechflow\AbandonedCart\Model\PopupRule as PopupRuleModel;
use Etechflow\AbandonedCart\Model\ResourceModel\PopupRule as PopupRuleResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'rule_id';

    protected function _construct(): void
    {
        $this->_init(PopupRuleModel::class, PopupRuleResource::class);
    }
}
