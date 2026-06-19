<?php
declare(strict_types=1);
/**
 * Etechflow_AbandonedCart — admin notification: pending abandoned carts
 */
namespace Etechflow\AbandonedCart\Model\System\Message;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Notification\MessageInterface;
use Magento\Backend\Model\UrlInterface;

class PendingCarts implements MessageInterface
{
    private ResourceConnection $resource;
    private UrlInterface $backendUrl;
    private ?int $count = null;

    public function __construct(ResourceConnection $resource, UrlInterface $backendUrl)
    {
        $this->resource   = $resource;
        $this->backendUrl = $backendUrl;
    }

    public function getIdentity(): string
    {
        return 'etechflow_abc_pending_carts';
    }

    public function isDisplayed(): bool
    {
        return $this->getPendingCount() > 0;
    }

    public function getText(): \Magento\Framework\Phrase
    {
        $count = $this->getPendingCount();
        $url   = $this->backendUrl->getUrl('etechflow_abandonedcart/cart/index');
        return __(
            '%1 abandoned cart(s) are pending recovery. <a href="%2">View now &rarr;</a>',
            $count,
            $url
        );
    }

    public function getSeverity(): int
    {
        return self::SEVERITY_NOTICE;
    }

    private function getPendingCount(): int
    {
        if ($this->count === null) {
            try {
                $conn        = $this->resource->getConnection();
                $table       = $conn->getTableName('etechflow_abandoned_cart');
                $this->count = (int) $conn->fetchOne(
                    "SELECT COUNT(*) FROM `{$table}` WHERE status = 'pending'"
                );
            } catch (\Exception $e) {
                $this->count = 0;
            }
        }
        return $this->count;
    }
}
