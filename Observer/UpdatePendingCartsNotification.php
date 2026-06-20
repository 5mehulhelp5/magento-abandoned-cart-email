<?php
declare(strict_types=1);
namespace Etechflow\AbandonedCart\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class UpdatePendingCartsNotification implements ObserverInterface
{
    private const TITLE_KEY = 'ETechFlow: pending abandoned carts';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            $conn  = $this->resource->getConnection();
            $table  = $conn->getTableName('etechflow_abandoned_cart');
            $inbox  = $conn->getTableName('adminnotification_inbox');
            $count  = (int) $conn->fetchOne("SELECT COUNT(*) FROM `{$table}` WHERE status = 1");

            $conn->delete($inbox, ["title = ?" => self::TITLE_KEY]);

            if ($count > 0) {
                $conn->insert($inbox, [
                    'severity'    => 4,
                    'date_added'  => date('Y-m-d H:i:s'),
                    'title'       => self::TITLE_KEY,
                    'description' => "{$count} abandoned cart(s) are pending email recovery.",
                    'url'         => 'etechflow_abandonedcart/cart/index',
                    'is_read'     => 0,
                    'is_remove'   => 0,
                ]);
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}
