<?php
/**
 * Etechflow_AbandonedCart - post-payment handler (webstore Paddle broker).
 *
 * The buyer returns here from the webstore Paddle checkout
 * (module.etechflow.com) carrying the broker session id. We POST it to the
 * broker's `/api/license/result` endpoint, which:
 *   1. Confirms with Paddle that the transaction was actually paid
 *   2. Has the portal mint/return the SP-XXXX license key
 *
 * On success: save the key + show the success page (key copy-to-clipboard).
 * On error:   show the error variant with a retry link.
 *
 * Same shape as the prior Stripe success -> portal activate flow; only the
 * money rail changed from Stripe to Paddle.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\License;

use Etechflow\AbandonedCart\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

class Activated extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::config';
    public const REGISTRY_KEY   = 'etechflow_abc_license_activated';

    private const BROKER_URL = 'https://module.etechflow.com/api/license/result';
    private const LICENSE_TOKEN = 'lcsk_8f3b9d2a7c14e605b9af2e7c1d8043f6';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly Curl $curl,
        private readonly LicenseValidator $licenseValidator,
        private readonly Registry $registry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $sessionId = (string) $this->getRequest()->getParam('session_id');
        $plan      = (string) $this->getRequest()->getParam('plan');

        $payload = [
            'error'          => null,
            'license_key'    => null,
            'plan'           => $plan,
            'settings_url'   => $this->getUrl('adminhtml/system_config/edit/section/etechflow_abandoned_cart'),
            'management_url' => $this->getUrl('etechflow_abandonedcart/cart/index'),
        ];

        try {
            if ($sessionId === '') {
                throw new \InvalidArgumentException('Missing payment session reference');
            }

            $body = json_encode(['session_id' => $sessionId], JSON_THROW_ON_ERROR);

            $this->curl->setOption(CURLOPT_TIMEOUT, 20);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('Accept', 'application/json');
            $this->curl->addHeader('X-ETF-License-Token', self::LICENSE_TOKEN);
            $this->curl->post(self::BROKER_URL, $body);

            $status = (int) $this->curl->getStatus();
            $response = json_decode((string) $this->curl->getBody(), true);

            if ($status !== 200 || !is_array($response) || empty($response['license_key'])) {
                $errorMsg = is_array($response) && !empty($response['error'])
                    ? (string) $response['error']
                    : 'Payment not confirmed yet (status ' . $status . ')';
                throw new \RuntimeException($errorMsg);
            }

            $key = (string) $response['license_key'];
            $this->licenseValidator->writeLicenseKey($key);
            $payload['license_key'] = $key;
            $payload['plan'] = (string) ($response['plan'] ?? $plan);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: license activation failed',
                ['exception' => $e->getMessage(), 'session_id' => $sessionId]
            );
            $payload['error'] = $e->getMessage();
        }

        $this->registry->register(self::REGISTRY_KEY, $payload, true);

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Etechflow_AbandonedCart::main');
        $page->getConfig()->getTitle()->prepend(
            $payload['error'] === null ? __('License Activated') : __('Activation Issue')
        );
        return $page;
    }
}
