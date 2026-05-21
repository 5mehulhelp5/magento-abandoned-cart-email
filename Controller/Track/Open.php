<?php
/**
 * Etechflow_AbandonedCart - Email open-tracking pixel.
 *
 * URL: `/etechflow_abandonedcart/track/open/?l=LOG_ID`
 *
 * Returns a 1×1 transparent GIF. Email clients fetch this image when the
 * email body renders, which is our signal that the email was opened.
 * Bumps `open_count`, sets `opened_at` on first open, flips `status` to
 * OPENED if it was previously SENT (don't downgrade past CLICKED/CONVERTED).
 *
 * The GIF bytes are inlined as a base64 constant — no filesystem dependency,
 * works the moment Magento bootstraps.
 *
 * Cache headers force a fresh fetch every open (some clients cache images
 * aggressively). Without `no-store`, the second open from the same client
 * wouldn't hit our endpoint and our count would undercount.
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
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class Open implements HttpGetActionInterface
{
    /**
     * 43-byte 1×1 transparent GIF (base64-encoded inline).
     */
    private const PIXEL_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly Config $config,
        private readonly EmailLogRepositoryInterface $emailLogRepo,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): ResultInterface
    {
        $span = Profiler::start('Etechflow_ABC_TrackOpen');

        try {
            if ($this->config->isEnabled() && $this->config->isOpenTrackingEnabled()) {
                $this->recordOpen();
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Etechflow_AbandonedCart: open tracking recording failed',
                ['exception' => $e->getMessage()]
            );
        } finally {
            Profiler::stop($span);
        }

        return $this->buildPixelResponse();
    }

    private function recordOpen(): void
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

        $log->setOpenCount($log->getOpenCount() + 1);
        if ($log->getOpenedAt() === null) {
            $log->setOpenedAt($this->dateTime->gmtDate());
        }

        // Status escalation only — don't downgrade past CLICKED/CONVERTED.
        if ($log->getStatus() === EmailLogInterface::STATUS_SENT) {
            $log->setStatus(EmailLogInterface::STATUS_OPENED);
        }

        $this->emailLogRepo->save($log);
    }

    private function buildPixelResponse(): ResultInterface
    {
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'image/gif', true);
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);
        $result->setHeader('Expires', '0', true);
        $result->setContents(base64_decode(self::PIXEL_GIF_BASE64));
        return $result;
    }
}
