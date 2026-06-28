<?php
/**
 * Etechflow_AbandonedCart - licensing checkout (webstore Stripe broker).
 *
 * Receives POST from gate.phtml with {plan, name, email}, then delegates to
 * the eTechFlow webstore licensing broker (module.etechflow.com). The broker
 * opens a Stripe transaction on the webstore's OWN Stripe account — price
 * pulled authoritatively from the licensing portal plan — and returns the
 * hosted pay URL. The portal still issues the SP-XXXX key once payment clears.
 *
 * On success → redirect to the Stripe hosted checkout.
 * On cancel  → returns to Gate.
 *
 * No card keys live in Magento. Same redirect flow as the prior Stripe
 * checkout; only the money rail is Stripe.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\License;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Checkout extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::config';

    private const MODULE_ID = 'abandoned-cart';
    private const BROKER_URL = 'https://module.etechflow.com/api/license/checkout';
    private const LICENSE_TOKEN = 'lcsk_8f3b9d2a7c14e605b9af2e7c1d8043f6';

    /** Allowed plan slugs — must match the portal's recurring plan slugs. */
    private const PLAN_SLUGS = ['abandoned_cart_weekly', 'abandoned_cart_monthly', 'abandoned_cart_yearly'];

    public function __construct(
        Context $context,
        private readonly Curl $curl,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $plan  = (string) $this->getRequest()->getParam('plan');
        $name  = trim((string) $this->getRequest()->getParam('name'));
        $email = trim((string) $this->getRequest()->getParam('email'));

        if (!in_array($plan, self::PLAN_SLUGS, true) || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->messageManager->addErrorMessage(__('Please select a plan and enter a valid email.'));
            return $redirect->setPath('etechflow_abandonedcart/license/gate');
        }

        $domain = $this->getDomain();

        // The broker validates the plan slug against the portal and resolves
        // its price there, so nothing is trusted from the browser but the slug.
        $payload = json_encode([
            'plan'             => $plan,
            'name'             => $name,
            'email'            => $email,
            'domain'           => $domain,
            'module'           => self::MODULE_ID,
            'magento_callback' => $this->getUrl('etechflow_abandonedcart/license/activated'),
            'magento_cancel'   => $this->getUrl('etechflow_abandonedcart/license/gate'),
        ]);

        try {
            $this->curl->setOption(CURLOPT_TIMEOUT, 20);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('Accept', 'application/json');
            $this->curl->addHeader('X-ETF-License-Token', self::LICENSE_TOKEN);
            $this->curl->post(self::BROKER_URL, $payload);
            $status = (int) $this->curl->getStatus();
            $data   = json_decode((string) $this->curl->getBody(), true);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: licensing checkout failed',
                ['exception' => $e->getMessage(), 'plan' => $plan]
            );
            $this->messageManager->addErrorMessage(__('Could not reach the licensing portal. Please try again.'));
            return $redirect->setPath('etechflow_abandonedcart/license/gate');
        }

        if ($status === 200 && is_array($data) && !empty($data['url'])) {
            return $redirect->setUrl((string) $data['url']);
        }

        $err = is_array($data) && !empty($data['error'])
            ? (string) $data['error']
            : ('Portal returned status ' . $status);
        $this->messageManager->addErrorMessage(__('Checkout error: %1', $err));
        return $redirect->setPath('etechflow_abandonedcart/license/gate');
    }

    private function getDomain(): string
    {
        $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        return strtolower(preg_replace('/^www\./', '', $host));
    }
}
