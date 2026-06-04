<?php
/**
 * Etechflow_AbandonedCart - Stripe Checkout session creator.
 *
 * Receives POST from gate.phtml with {plan, name, email}, decrypts the
 * admin's Stripe secret key, creates a Stripe Checkout Session via direct
 * cURL, then redirects the merchant's browser to Stripe to enter card
 * details.
 *
 * On Stripe success → returns to Activated controller.
 * On Stripe cancel  → returns to Gate.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\License;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Checkout extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::config';

    private const STRIPE_API = 'https://api.stripe.com/v1/checkout/sessions';

    public function __construct(
        Context $context,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
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

        if (!in_array($plan, ['weekly', 'monthly', 'yearly'], true) || $email === '') {
            $this->messageManager->addErrorMessage(__('Please select a plan and enter your email.'));
            return $redirect->setPath('etechflow_abandonedcart/license/gate');
        }

        try {
            $secretKeyEncrypted = (string) $this->scopeConfig->getValue(
                'etechflow_abandoned_cart/payment/stripe_secret_key',
                ScopeInterface::SCOPE_STORE
            );
            if ($secretKeyEncrypted === '') {
                $this->messageManager->addErrorMessage(__('Stripe Secret Key is not configured. Set it under Stores → Configuration → ETechFlow → Payment Settings.'));
                return $redirect->setPath('etechflow_abandonedcart/license/gate');
            }

            $stripeKey = $this->encryptor->decrypt($secretKeyEncrypted);
            $currency  = strtolower((string) $this->scopeConfig->getValue(
                'etechflow_abandoned_cart/payment/stripe_currency',
                ScopeInterface::SCOPE_STORE
            ) ?: 'usd');

            $price = $this->resolvePrice($plan);
            if ($price <= 0) {
                $this->messageManager->addErrorMessage(__('The selected plan has no price configured.'));
                return $redirect->setPath('etechflow_abandonedcart/license/gate');
            }

            $domain = $this->getDomain();
            $sessionUrl = $this->createStripeSession($stripeKey, $currency, $price, $plan, $name, $email, $domain);

            return $redirect->setUrl($sessionUrl);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: Stripe checkout failed',
                ['exception' => $e->getMessage(), 'plan' => $plan]
            );
            $this->messageManager->addErrorMessage(__('Could not start checkout: %1', $e->getMessage()));
            return $redirect->setPath('etechflow_abandonedcart/license/gate');
        }
    }

    private function resolvePrice(string $plan): int
    {
        $path = 'etechflow_abandoned_cart/plans/' . $plan . '_price';
        $raw = (string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
        $amount = (float) $raw;
        // Stripe expects integer cents
        return (int) round($amount * 100);
    }

    private function getDomain(): string
    {
        $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        return strtolower(preg_replace('/^www\./', '', $host));
    }

    /**
     * Build a Stripe Checkout Session via direct cURL. Returns the redirect URL.
     */
    private function createStripeSession(
        string $stripeKey,
        string $currency,
        int $amountCents,
        string $plan,
        string $name,
        string $email,
        string $domain
    ): string {
        // Generate admin URLs through the URL builder so Magento embeds the
        // per-session secret key (/key/<hex>/). A hand-concatenated path is
        // missing that segment and the admin guard silently bounces the
        // off-site return to the dashboard. Stripe needs the literal
        // {CHECKOUT_SESSION_ID} token preserved, so we substitute it back in
        // after the URL builder has done its work.
        $sessionPlaceholder = 'STRIPESESSIONIDPLACEHOLDER';
        $successUrl = $this->_url->getUrl('etechflow_abandonedcart/license/activated', [
            'session_id' => $sessionPlaceholder,
            'plan'       => $plan,
            'domain'     => $domain,
            'name'       => $name,
            'email'      => $email,
        ]);
        $successUrl = str_replace($sessionPlaceholder, '{CHECKOUT_SESSION_ID}', $successUrl);
        $cancelUrl = $this->_url->getUrl('etechflow_abandonedcart/license/gate');

        $body = http_build_query([
            'mode'                                       => 'subscription',
            'customer_email'                             => $email,
            'success_url'                                => $successUrl,
            'cancel_url'                                 => $cancelUrl,
            'line_items[0][price_data][currency]'        => $currency,
            'line_items[0][price_data][product_data][name]' => 'Etechflow Abandoned Cart — ' . ucfirst($plan),
            'line_items[0][price_data][unit_amount]'     => (string) $amountCents,
            'line_items[0][price_data][recurring][interval]' => $this->stripeInterval($plan),
            'line_items[0][quantity]'                    => '1',
            'metadata[domain]'                           => $domain,
            'metadata[plan]'                             => $plan,
            'metadata[contact_name]'                     => $name,
        ]);

        $this->curl->setOption(CURLOPT_TIMEOUT, 15);
        $this->curl->addHeader('Authorization', 'Bearer ' . $stripeKey);
        $this->curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->curl->post(self::STRIPE_API, $body);

        $response = (string) $this->curl->getBody();
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['url'])) {
            $errorMsg = $data['error']['message'] ?? 'Unknown Stripe error';
            throw new \RuntimeException($errorMsg);
        }
        return (string) $data['url'];
    }

    private function stripeInterval(string $plan): string
    {
        return match ($plan) {
            'weekly'  => 'week',
            'monthly' => 'month',
            'yearly'  => 'year',
            default   => 'month',
        };
    }
}
