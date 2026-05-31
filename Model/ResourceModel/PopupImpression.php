<?php
/**
 * Etechflow_AbandonedCart - PopupImpression resource model.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PopupImpression extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('etechflow_popup_impression', 'impression_id');
    }
}
