<?php
/**
 * Etechflow_AbandonedCart - EmailLog collection.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel\EmailLog;

use Etechflow\AbandonedCart\Model\EmailLog as EmailLogModel;
use Etechflow\AbandonedCart\Model\ResourceModel\EmailLog as EmailLogResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'log_id';

    protected function _construct(): void
    {
        $this->_init(EmailLogModel::class, EmailLogResource::class);
    }
}
