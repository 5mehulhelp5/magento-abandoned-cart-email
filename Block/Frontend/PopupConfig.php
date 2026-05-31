<?php
/**
 * Etechflow_AbandonedCart - Exposes popup runtime config to frontend JS.
 *
 * Rendered into every frontend page via [[view/frontend/layout/default.xml]]
 * (Luma) and [[view/frontend/layout/hyva_default.xml]] (Hyvä).
 *
 * The template emits a <script type="application/json"> tag — both the
 * Knockout (Luma) and Alpine (Hyvä) popup components read it on init.
 *
 * Config payload (kept intentionally minimal — admin-only fields like
 * priority, conditions, etc. never reach the browser):
 *   {
 *     enabled    : bool,
 *     store_id   : int,
 *     page_scope : string ('cart'|'checkout'|'category'|'product'|'home'|'other'|'all'),
 *     device_type: string ('desktop'|'mobile'|'tablet'),
 *     urls       : { get, track, apply }
 *   }
 *
 * Disabled state still renders the block (with enabled=false) so the JS
 * can bail out gracefully without hard-erroring on a missing config.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block\Frontend;

use Etechflow\AbandonedCart\Api\Data\PopupImpressionInterface;
use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\HTTP\Header as HttpHeader;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Store\Model\StoreManagerInterface;

class PopupConfig extends Template
{
    /**
     * Map full action name → page_scope value. Anything not in this map
     * falls back to SCOPE_ALL ('all') so an "all-pages" rule still fires.
     */
    private const PAGE_SCOPE_MAP = [
        'checkout_cart_index'   => PopupRuleInterface::SCOPE_CART,
        'checkout_index_index'  => PopupRuleInterface::SCOPE_CHECKOUT,
        'catalog_category_view' => PopupRuleInterface::SCOPE_CATEGORY,
        'catalog_product_view'  => PopupRuleInterface::SCOPE_PRODUCT,
    ];

    public function __construct(
        TemplateContext $context,
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpContext $httpContext,
        private readonly HttpHeader $httpHeader,
        private readonly EncoderInterface $jsonEncoder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfigJson(): string
    {
        return $this->jsonEncoder->encode($this->buildConfig());
    }

    /**
     * @return array<string, mixed>
     */
    public function buildConfig(): array
    {
        $enabled = $this->config->isEnabled() && $this->licenseValidator->isValid();

        return [
            'enabled'     => $enabled,
            'store_id'    => (int) $this->storeManager->getStore()->getId(),
            'page_scope'  => $this->detectPageScope(),
            'device_type' => $this->detectDeviceType(),
            'urls'        => [
                'get'   => $this->getUrl('etechflow_abandonedcart/popup/get'),
                'track' => $this->getUrl('etechflow_abandonedcart/popup/track'),
                'apply' => $this->getUrl('etechflow_abandonedcart/popup/apply'),
            ],
        ];
    }

    private function detectPageScope(): string
    {
        $fullAction = (string) $this->getRequest()->getFullActionName();
        return self::PAGE_SCOPE_MAP[$fullAction] ?? PopupRuleInterface::SCOPE_ALL;
    }

    /**
     * Coarse User-Agent classification — desktop / mobile / tablet. The
     * server side only needs this for impression analytics; the JS popup
     * doesn't render differently per device.
     */
    private function detectDeviceType(): string
    {
        $ua = strtolower((string) $this->httpHeader->getHttpUserAgent());
        if ($ua === '') {
            return PopupImpressionInterface::DEVICE_DESKTOP;
        }
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return PopupImpressionInterface::DEVICE_TABLET;
        }
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return PopupImpressionInterface::DEVICE_MOBILE;
        }
        return PopupImpressionInterface::DEVICE_DESKTOP;
    }
}
