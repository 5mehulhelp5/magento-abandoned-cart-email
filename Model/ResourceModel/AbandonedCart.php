<?php
/**
 * Etechflow_AbandonedCart - AbandonedCart resource model.
 *
 * Thin AbstractDb wrapper that points the model at its db_schema.xml table +
 * primary key. All schema details (columns, FKs, indexes) live in
 * etc/db_schema.xml — keeping them out of PHP follows declarative-schema
 * best practice.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AbandonedCart extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('etechflow_abandoned_cart', 'entity_id');
    }
}
