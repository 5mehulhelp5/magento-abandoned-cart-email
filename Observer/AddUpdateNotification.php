<?php
declare(strict_types=1);
namespace Etechflow\AbandonedCart\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Adds an admin bell-icon entry when a newer version of this module is
 * available on the eTechFlow private Composer repo. Fires when the Abandoned
 * Cart admin page is opened. Same pattern as ETechFlow\ImageOptimizer.
 */
class AddUpdateNotification implements ObserverInterface
{
    private const PACKAGE    = 'etechflow/module-abandoned-cart';
    private const LATEST_URL = 'https://license-service.etechflow.com/composer/latest/etechflow/module-abandoned-cart.json';
    private const CACHE_KEY  = 'etechflow_abc_latest_version';
    private const CACHE_TTL  = 21600; // 6 hours
    private const MODULE_NAME = 'Etechflow_AbandonedCart';

    public function __construct(
        private readonly NotifierInterface $notifier,
        private readonly CurlFactory $curlFactory,
        private readonly CacheInterface $cache,
        private readonly ResourceConnection $resource
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            $latest = $this->fetchLatest();
            if (empty($latest['version'])) {
                return;
            }
            $installed = $this->installedVersion();
            if ($installed === '' || version_compare($installed, $latest['version'], '>=')) {
                return;
            }
            $title = (string) __('eTechFlow Abandoned Cart %1 is available', $latest['version']);

            $conn  = $this->resource->getConnection();
            $table = $this->resource->getTableName('adminnotification_inbox');
            $exists = (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM {$table} WHERE title = ? AND is_remove = 0",
                [$title]
            );
            if ($exists > 0) {
                return;
            }

            $desc = !empty($latest['notes'])
                ? $latest['notes']
                : (string) __(
                    'A new version (%1) is available — you have %2. Update with: composer update %3',
                    $latest['version'],
                    $installed,
                    self::PACKAGE
                );
            $this->notifier->addNotice($title, $desc);
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    private function fetchLatest(): array
    {
        $raw = $this->cache->load(self::CACHE_KEY);
        if (!$raw) {
            $raw = '{}';
            try {
                $curl = $this->curlFactory->create();
                $curl->setTimeout(5);
                $curl->get(self::LATEST_URL);
                if ((int) $curl->getStatus() === 200) {
                    $raw = (string) $curl->getBody();
                }
            } catch (\Throwable $e) {
                $raw = '{}';
            }
            $this->cache->save($raw, self::CACHE_KEY, [], self::CACHE_TTL);
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['latest_version'])) {
            return ['version' => '', 'notes' => ''];
        }
        return ['version' => (string)$data['latest_version'], 'notes' => (string)($data['release_notes'] ?? '')];
    }

    private function installedVersion(): string
    {
        try {
            $conn = $this->resource->getConnection();
            $table = $this->resource->getTableName('setup_module');
            $v = $conn->fetchOne(
                "SELECT schema_version FROM {$table} WHERE module = ?",
                [self::MODULE_NAME]
            );
            return $v ? (string) $v : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
