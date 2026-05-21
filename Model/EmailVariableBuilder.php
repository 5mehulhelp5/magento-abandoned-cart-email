<?php
/**
 * Etechflow_AbandonedCart - Email template variable builder.
 *
 * Single source of truth for the `[$var => value]` array that gets
 * shipped to each abandoned-cart email template. EmailSender owns the
 * Transport plumbing; this class owns "what does the template see".
 *
 * URLs assembled here MUST match the routes defined in Phase 13's
 * etc/frontend/routes.xml — token format and query-arg names are the
 * contract between the two phases. Drift = broken links in emails.
 *
 * Route plan (Phase 13 implements):
 *   {base}etechflow_abandonedcart/restore/index/?t={token}
 *   {base}etechflow_abandonedcart/unsubscribe/index/?t={token}
 *   {base}etechflow_abandonedcart/track/open/?l={log_id}
 *   {base}etechflow_abandonedcart/track/click/?l={log_id}&u={destination}
 *
 * UTM parameters are appended to the restore link only — tracking pixels
 * and unsubscribe links don't need them (they're not visited as outbound
 * "campaign" clicks; they're internal mechanisms).
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Magento\Store\Model\StoreManagerInterface;

class EmailVariableBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        AbandonedCartInterface $cart,
        EmailLogInterface $log,
        ?string $couponCode = null,
        ?string $couponLabel = null
    ): array {
        $storeId  = $cart->getStoreId();
        $store    = $this->storeManager->getStore($storeId);
        $baseUrl  = rtrim((string) $store->getBaseUrl(), '/') . '/';
        $token    = $cart->getRestoreToken();

        return [
            'customer_firstname' => (string) ($cart->getCustomerFirstname() ?? ''),
            'cart'               => $cart,
            'restore_url'        => $this->buildRestoreUrl($baseUrl, $token, $storeId),
            'unsubscribe_url'    => $this->buildUnsubscribeUrl($baseUrl, $token),
            'tracking_pixel_url' => $this->buildTrackingPixelUrl($baseUrl, $log, $storeId),
            'coupon_code'        => (string) ($couponCode ?? ''),
            'coupon_label'       => (string) ($couponLabel ?? ''),
        ];
    }

    private function buildRestoreUrl(string $baseUrl, string $token, int $storeId): string
    {
        $query = http_build_query([
            't'            => $token,
            'utm_source'   => $this->config->getUtmSource($storeId),
            'utm_medium'   => $this->config->getUtmMedium($storeId),
            'utm_campaign' => $this->config->getUtmCampaign($storeId),
        ]);
        return $baseUrl . 'etechflow_abandonedcart/restore/index/?' . $query;
    }

    private function buildUnsubscribeUrl(string $baseUrl, string $token): string
    {
        return $baseUrl . 'etechflow_abandonedcart/unsubscribe/index/?t=' . rawurlencode($token);
    }

    private function buildTrackingPixelUrl(string $baseUrl, EmailLogInterface $log, int $storeId): ?string
    {
        if (!$this->config->isOpenTrackingEnabled($storeId)) {
            return null;
        }
        if ($log->getLogId() === null) {
            return null;
        }
        return $baseUrl . 'etechflow_abandonedcart/track/open/?l=' . $log->getLogId();
    }
}
