<?php
/**
 * Etechflow_AbandonedCart - Post-Stripe-success handler.
 *
 * Stripe redirects here with `?session_id={CHECKOUT_SESSION_ID}` after the
 * customer completes payment. We then POST to the eTechFlow Portal's
 * `/license/activate` endpoint to:
 *   1. Verify the Stripe session is paid
 *   2. Create/update the subscription row in the portal DB
 *   3. Mint and return the SP-XXXX license key
 *
 * On success: save the key to license_key + show success page with the
 * key visible (copy-to-clipboard button in phtml).
 * On error: show error variant with retry link.
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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class Activated extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::config';
    public const REGISTRY_KEY   = 'etechflow_abc_license_activated';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
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
        $domain    = (string) $this->getRequest()->getParam('domain');
        $name      = (string) $this->getRequest()->getParam('name');
        $email     = (string) $this->getRequest()->getParam('email');

        $payload = [
            'error'           => null,
            'license_key'     => null,
            'plan'            => $plan,
            'settings_url'    => $this->getSettingsUrl(),
            'management_url'  => $this->getUrl('etechflow_abandonedcart/cart/index'),
        ];

        try {
            if ($sessionId === '' || $domain === '' || $plan === '') {
                throw new \InvalidArgumentException('Missing required parameters from Stripe redirect');
            }

            $stripeKeyEncrypted = (string) $this->scopeConfig->getValue(
                'etechflow_abandoned_cart/payment/stripe_secret_key',
                ScopeInterface::SCOPE_STORE
            );
            if ($stripeKeyEncrypted === '') {
                throw new \RuntimeException('Stripe Secret Key not configured');
            }
            $stripeKey = $this->encryptor->decrypt($stripeKeyEncrypted);

            $portalBase = str_replace('/license/validate', '', $this->licenseValidator->getPortalUrl());
            $activateUrl = rtrim($portalBase, '/') . '/license/activate';

            $body = json_encode([
                'session_id'        => $sessionId,
                'stripe_secret_key' => $stripeKey,
                'domain'            => $domain,
                'plan'              => $plan,
                'name'              => $name,
                'email'             => $email,
                'platform'          => 'magento',
                'module'            => LicenseValidator::MODULE_ID,
            ], JSON_THROW_ON_ERROR);

            $this->curl->setOption(CURLOPT_TIMEOUT, 20);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('Accept', 'application/json');
            $this->curl->post($activateUrl, $body);

            $status = (int) $this->curl->getStatus();
            $response = json_decode((string) $this->curl->getBody(), true);

            if ($status !== 200 || !is_array($response) || empty($response['license_key'])) {
                $errorMsg = is_array($response) && !empty($response['error'])
                    ? (string) $response['error']
                    : 'Portal returned status ' . $status;
                throw new \RuntimeException($errorMsg);
            }

            $key = (string) $response['license_key'];
            $this->licenseValidator->writeLicenseKey($key);
            $payload['license_key'] = $key;
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

    private function getSettingsUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit/section/etechflow_abandoned_cart');
    }
}
