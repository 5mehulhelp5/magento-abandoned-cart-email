<?php
/**
 * Etechflow_AbandonedCart - PopupImpression collection.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel\PopupImpression;

use Etechflow\AbandonedCart\Model\PopupImpression as PopupImpressionModel;
use Etechflow\AbandonedCart\Model\ResourceModel\PopupImpression as PopupImpressionResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'impression_id';

    protected function _construct(): void
    {
        $this->_init(PopupImpressionModel::class, PopupImpressionResource::class);
    }
}
