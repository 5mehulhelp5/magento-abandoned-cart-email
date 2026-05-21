<?php
/**
 * Etechflow_AbandonedCart - Email click-tracking redirect.
 *
 * URL: `/etechflow_abandonedcart/track/click/?l=LOG_ID&u=DESTINATION_URL`
 *
 * Counts the click on `email_log` then redirects to the destination. Status
 * escalates SENT→OPENED→CLICKED (don't downgrade past CONVERTED).
 *
 * Open-redirect mitigation:
 *   - Destination must be non-empty
 *   - Destination must start with http:// or https://
 *   - Destination host must match an allow-listed store base URL (otherwise
 *     attacker could craft a tracked link pointing to a phishing site)
 *
 * If validation fails, redirect to the store homepage instead of the
 * untrusted URL — fail-safe.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Track;

use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Api\EmailLogRepositoryInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Click implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly Config $config,
        private readonly EmailLogRepositoryInterface $emailLogRepo,
        private readonly StoreManagerInterface $storeManager,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): ResultInterface
    {
        $span = Profiler::start('Etechflow_ABC_TrackClick');
        $redirect = $this->redirectFactory->create();

        try {
            if ($this->config->isEnabled() && $this->config->isClickTrackingEnabled()) {
                $this->recordClick();
            }

            $destination = (string) $this->request->getParam('u');
            if ($this->isSafeDestination($destination)) {
                return $redirect->setUrl($destination);
            }

            return $redirect->setPath('/');
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Etechflow_AbandonedCart: click tracking failed',
                ['exception' => $e->getMessage()]
            );
            return $redirect->setPath('/');
        } finally {
            Profiler::stop($span);
        }
    }

    private function recordClick(): void
    {
        $logId = (int) $this->request->getParam('l');
        if ($logId <= 0) {
            return;
        }

        try {
            $log = $this->emailLogRepo->getById($logId);
        } catch (NoSuchEntityException) {
            return;
        }

        $log->setClickCount($log->getClickCount() + 1);
        if ($log->getClickedAt() === null) {
            $log->setClickedAt($this->dateTime->gmtDate());
        }

        if (in_array(
            $log->getStatus(),
            [EmailLogInterface::STATUS_SENT, EmailLogInterface::STATUS_OPENED],
            true
        )) {
            $log->setStatus(EmailLogInterface::STATUS_CLICKED);
        }

        $this->emailLogRepo->save($log);
    }

    /**
     * Allow-list redirect destinations: must be http/https AND host must
     * match one of the configured store base URLs. Prevents abuse of
     * this endpoint as an open-redirect for phishing.
     */
    private function isSafeDestination(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $destHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($destHost === '') {
            return false;
        }

        foreach ($this->storeManager->getStores() as $store) {
            $baseHost = strtolower((string) parse_url((string) $store->getBaseUrl(), PHP_URL_HOST));
            if ($baseHost !== '' && $baseHost === $destHost) {
                return true;
            }
        }
        return false;
    }
}
