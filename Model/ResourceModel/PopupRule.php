<?php
/**
 * Etechflow_AbandonedCart - PopupRule resource model.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PopupRule extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('etechflow_popup_rule', 'rule_id');
    }
}
