<?php
/**
 * Etechflow_AbandonedCart - Typed configuration wrapper.
 *
 * Per ETechFlow Module Development Standards §5: every business-logic class
 * (observer, plugin, cron, controller, sender) injects this Config wrapper,
 * NOT Magento\Framework\App\Config\ScopeConfigInterface directly. This keeps
 * config paths in one file, returns are strongly typed, and store-scope
 * handling is consistent.
 *
 * Scope: most fields are store-scoped (?int $storeId) so per-store-view
 * overrides work. Kill-switches that span every store stay store-scoped too
 * for predictability — opt-in to default scope via passing $storeId = 0 if
 * truly module-wide.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    // -------------------------------------------------------------------
    // General
    // -------------------------------------------------------------------
    public const XML_PATH_GENERAL_ENABLED                = 'etechflow_abandoned_cart/general/enabled';
    public const XML_PATH_GENERAL_THRESHOLD_MINUTES      = 'etechflow_abandoned_cart/general/abandonment_threshold_minutes';
    public const XML_PATH_GENERAL_DEBUG                  = 'etechflow_abandoned_cart/general/debug';
    public const XML_PATH_GENERAL_TEST_MODE              = 'etechflow_abandoned_cart/general/test_mode';
    public const XML_PATH_GENERAL_TEST_RECIPIENT_EMAIL   = 'etechflow_abandoned_cart/general/test_recipient_email';

    // -------------------------------------------------------------------
    // Email
    // -------------------------------------------------------------------
    public const XML_PATH_EMAIL_SENDER_NAME              = 'etechflow_abandoned_cart/email/sender_name';
    public const XML_PATH_EMAIL_SENDER_IDENTITY          = 'etechflow_abandoned_cart/email/sender_identity';
    public const XML_PATH_EMAIL_REPLY_TO                 = 'etechflow_abandoned_cart/email/reply_to';
    public const XML_PATH_EMAIL_BCC                      = 'etechflow_abandoned_cart/email/bcc';
    public const XML_PATH_EMAIL_MAX_PER_CART             = 'etechflow_abandoned_cart/email/max_emails_per_cart';
    public const XML_PATH_EMAIL_DEFAULT_TEMPLATE         = 'etechflow_abandoned_cart/email/default_template';

    // -------------------------------------------------------------------
    // Restore
    // -------------------------------------------------------------------
    public const XML_PATH_RESTORE_TOKEN_EXPIRY_DAYS      = 'etechflow_abandoned_cart/restore/token_expiry_days';
    public const XML_PATH_RESTORE_AUTO_LOGIN             = 'etechflow_abandoned_cart/restore/auto_login_customer';
    public const XML_PATH_RESTORE_MERGE_EXISTING_CART    = 'etechflow_abandoned_cart/restore/merge_with_existing_cart';

    // -------------------------------------------------------------------
    // Tracking
    // -------------------------------------------------------------------
    public const XML_PATH_TRACKING_OPEN_ENABLED          = 'etechflow_abandoned_cart/tracking/enable_open_tracking';
    public const XML_PATH_TRACKING_CLICK_ENABLED         = 'etechflow_abandoned_cart/tracking/enable_click_tracking';
    public const XML_PATH_TRACKING_UTM_SOURCE            = 'etechflow_abandoned_cart/tracking/utm_source';
    public const XML_PATH_TRACKING_UTM_MEDIUM            = 'etechflow_abandoned_cart/tracking/utm_medium';
    public const XML_PATH_TRACKING_UTM_CAMPAIGN          = 'etechflow_abandoned_cart/tracking/utm_campaign';

    // -------------------------------------------------------------------
    // Cron
    // -------------------------------------------------------------------
    public const XML_PATH_CRON_BATCH_SIZE                = 'etechflow_abandoned_cart/cron/batch_size';
    public const XML_PATH_CRON_LOCK_TIMEOUT_MINUTES      = 'etechflow_abandoned_cart/cron/lock_timeout_minutes';
    public const XML_PATH_CRON_MAX_RUNTIME_SECONDS       = 'etechflow_abandoned_cart/cron/max_runtime_seconds';

    // -------------------------------------------------------------------
    // Cleanup
    // -------------------------------------------------------------------
    public const XML_PATH_CLEANUP_LOG_RETENTION_DAYS     = 'etechflow_abandoned_cart/cleanup/log_retention_days';
    public const XML_PATH_CLEANUP_EXPIRED_CART_RETENTION = 'etechflow_abandoned_cart/cleanup/expired_cart_retention_days';

    // -------------------------------------------------------------------
    // Hyvä
    // -------------------------------------------------------------------
    public const XML_PATH_HYVA_ENABLED                   = 'etechflow_abandoned_cart/hyva/enabled';

    // -------------------------------------------------------------------
    // License (consumed by [[Etechflow\AbandonedCart\Model\LicenseValidator]])
    // -------------------------------------------------------------------
    public const XML_PATH_LICENSE_KEY                    = 'etechflow_abandoned_cart/license/key';
    public const XML_PATH_LICENSE_IS_PRODUCTION          = 'etechflow_abandoned_cart/license/is_production';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    // -------------------------------------------------------------------
    // General
    // -------------------------------------------------------------------

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERAL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAbandonmentThresholdMinutes(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_GENERAL_THRESHOLD_MINUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDebugMode(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERAL_DEBUG,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isTestMode(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERAL_TEST_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getTestRecipientEmail(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_GENERAL_TEST_RECIPIENT_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // -------------------------------------------------------------------
    // Email
    // -------------------------------------------------------------------

    public function getSenderName(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_SENDER_NAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSenderIdentity(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_SENDER_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getReplyToEmail(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_REPLY_TO,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getBccEmail(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_BCC,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getMaxEmailsPerCart(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_MAX_PER_CART,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getDefaultEmailTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_DEFAULT_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // -------------------------------------------------------------------
    // Restore
    // -------------------------------------------------------------------

    public function getRestoreTokenExpiryDays(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_RESTORE_TOKEN_EXPIRY_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAutoLoginCustomerOnRestore(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_RESTORE_AUTO_LOGIN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isMergeWithExistingCart(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_RESTORE_MERGE_EXISTING_CART,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // -------------------------------------------------------------------
    // Tracking
    // -------------------------------------------------------------------

    public function isOpenTrackingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACKING_OPEN_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isClickTrackingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACKING_CLICK_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getUtmSource(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TRACKING_UTM_SOURCE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getUtmMedium(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TRACKING_UTM_MEDIUM,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getUtmCampaign(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TRACKING_UTM_CAMPAIGN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // -------------------------------------------------------------------
    // Cron
    // -------------------------------------------------------------------

    public function getCronBatchSize(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CRON_BATCH_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCronLockTimeoutMinutes(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CRON_LOCK_TIMEOUT_MINUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCronMaxRuntimeSeconds(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CRON_MAX_RUNTIME_SECONDS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // -------------------------------------------------------------------
    // Cleanup
    // -------------------------------------------------------------------

    public function getLogRetentionDays(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CLEANUP_LOG_RETENTION_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getExpiredCartRetentionDays(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CLEANUP_EXPIRED_CART_RETENTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // -------------------------------------------------------------------
    // Hyvä
    // -------------------------------------------------------------------

    public function isHyvaEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HYVA_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // -------------------------------------------------------------------
    // License
    // -------------------------------------------------------------------

    /**
     * License key as stored in admin config. Empty string when unset.
     * Pasted by the merchant after purchase or generated via
     * tools/generate-license.php for development. Validation happens in
     * [[Etechflow\AbandonedCart\Model\LicenseValidator]].
     */
    public function getLicenseKey(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_LICENSE_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * When false, the LicenseValidator auto-passes regardless of key. Lets
     * staging/dev environments run without a real key when they don't
     * happen to be on a recognized dev-host pattern.
     */
    public function isProductionEnvironment(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_LICENSE_IS_PRODUCTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // -------------------------------------------------------------------
    // Sender identity passthrough (Magento standard trans_email/ident_*)
    // -------------------------------------------------------------------

    /**
     * Magento ships sender identities at trans_email/ident_<identity>/email.
     * Standard identities: general, sales, support, custom1, custom2.
     * We read them through this wrapper so EmailSender stays compliant with
     * §5 (no direct ScopeConfigInterface in business logic).
     */
    public function getSenderEmailFromIdentity(string $identity, ?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            'trans_email/ident_' . $identity . '/email',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSenderNameFromIdentity(string $identity, ?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            'trans_email/ident_' . $identity . '/name',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
