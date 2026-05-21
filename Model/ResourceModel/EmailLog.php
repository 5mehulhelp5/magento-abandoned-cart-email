<?php
/**
 * Etechflow_AbandonedCart - EmailLog resource model.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class EmailLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('etechflow_abandoned_cart_email_log', 'log_id');
    }
}
