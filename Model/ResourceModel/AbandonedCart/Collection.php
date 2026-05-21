<?php
/**
 * Etechflow_AbandonedCart - AbandonedCart collection.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel\AbandonedCart;

use Etechflow\AbandonedCart\Model\AbandonedCart as AbandonedCartModel;
use Etechflow\AbandonedCart\Model\ResourceModel\AbandonedCart as AbandonedCartResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(AbandonedCartModel::class, AbandonedCartResource::class);
    }
}
