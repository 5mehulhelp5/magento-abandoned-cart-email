<?php
/**
 * Etechflow_AbandonedCart - PopupRule data interface.
 *
 * Service contract for v1.1.0's exit-intent / on-page discount popup rules.
 * Each row is an admin-managed rule that decides WHEN, WHERE, and TO WHOM
 * a popup is displayed on the storefront. Discount delivery is handled via
 * a linked Magento sales rule (sales_rule_id field).
 *
 * Constants mirror etc/db_schema.xml `etechflow_popup_rule` table + enum
 * values for trigger_type, page_scope, frequency.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Api\Data;

interface PopupRuleInterface
{
    public const RULE_ID                       = 'rule_id';
    public const NAME                          = 'name';
    public const DESCRIPTION                   = 'description';
    public const IS_ACTIVE                     = 'is_active';
    public const PRIORITY                      = 'priority';
    public const TRIGGER_TYPE                  = 'trigger_type';
    public const TRIGGER_VALUE                 = 'trigger_value';
    public const PAGE_SCOPE                    = 'page_scope';
    public const MIN_CART_SUBTOTAL             = 'min_cart_subtotal';
    public const MAX_CART_SUBTOTAL             = 'max_cart_subtotal';
    public const STORE_IDS                     = 'store_ids';
    public const CUSTOMER_GROUP_IDS            = 'customer_group_ids';
    public const POPUP_HEADLINE                = 'popup_headline';
    public const POPUP_BODY                    = 'popup_body';
    public const POPUP_CTA_TEXT                = 'popup_cta_text';
    public const POPUP_IMAGE_URL               = 'popup_image_url';
    public const SALES_RULE_ID                 = 'sales_rule_id';
    public const FREQUENCY                     = 'frequency';
    public const MAX_IMPRESSIONS_PER_CUSTOMER  = 'max_impressions_per_customer';
    public const APPLY_TO_GUESTS               = 'apply_to_guests';

    /* Visual design (v1.2.0) */
    public const TEMPLATE_LAYOUT               = 'template_layout';
    public const BG_COLOR                      = 'bg_color';
    public const HEADLINE_COLOR                = 'headline_color';
    public const BODY_COLOR                    = 'body_color';
    public const CTA_BG_COLOR                  = 'cta_bg_color';
    public const CTA_TEXT_COLOR                = 'cta_text_color';
    public const BORDER_RADIUS                 = 'border_radius';
    public const DIALOG_WIDTH                  = 'dialog_width';
    public const ANIMATION_TYPE                = 'animation_type';

    /* Mobile (v1.2.0) */
    public const MOBILE_FALLBACK_SECONDS       = 'mobile_fallback_seconds';

    /* Layout enum values */
    public const LAYOUT_MODAL                  = 'modal';
    public const LAYOUT_SLIDE_IN               = 'slide_in';
    public const LAYOUT_BOTTOM_BAR             = 'bottom_bar';
    public const LAYOUT_TOP_BAR                = 'top_bar';

    /* Animation enum values */
    public const ANIMATION_FADE_IN             = 'fade_in';
    public const ANIMATION_SLIDE_UP            = 'slide_up';
    public const ANIMATION_ZOOM_IN             = 'zoom_in';
    public const ANIMATION_BOUNCE              = 'bounce';

    public const CREATED_AT                    = 'created_at';
    public const UPDATED_AT                    = 'updated_at';

    public const TRIGGER_EXIT_INTENT           = 'exit_intent';
    public const TRIGGER_TIME_ON_PAGE          = 'time_on_page';
    public const TRIGGER_SCROLL_DEPTH          = 'scroll_depth';
    public const TRIGGER_CART_SUBTOTAL         = 'cart_subtotal_threshold';

    public const SCOPE_ALL                     = 'all';
    public const SCOPE_CART                    = 'cart';
    public const SCOPE_CHECKOUT                = 'checkout';
    public const SCOPE_CATEGORY                = 'category';
    public const SCOPE_PRODUCT                 = 'product';

    public const FREQUENCY_ONCE_PER_SESSION    = 'once_per_session';
    public const FREQUENCY_ONCE_PER_DAY        = 'once_per_day';
    public const FREQUENCY_ONCE_PER_LIFETIME   = 'once_per_lifetime';

    /** @return int|null */
    public function getRuleId(): ?int;

    /**
     * Untyped param to satisfy LSP with AbstractModel::setId().
     * @param int $id
     * @return self
     */
    public function setRuleId($id): self;

    /** @return string */
    public function getName(): string;

    /** @return self */
    public function setName(string $name): self;

    /** @return string|null */
    public function getDescription(): ?string;

    /** @return self */
    public function setDescription(?string $description): self;

    /** @return bool */
    public function isActive(): bool;

    /** @return self */
    public function setIsActive(bool $isActive): self;

    /** @return int */
    public function getPriority(): int;

    /** @return self */
    public function setPriority(int $priority): self;

    /** @return string */
    public function getTriggerType(): string;

    /** @return self */
    public function setTriggerType(string $triggerType): self;

    /** @return int|null */
    public function getTriggerValue(): ?int;

    /** @return self */
    public function setTriggerValue(?int $value): self;

    /** @return string */
    public function getPageScope(): string;

    /** @return self */
    public function setPageScope(string $scope): self;

    /** @return float|null */
    public function getMinCartSubtotal(): ?float;

    /** @return self */
    public function setMinCartSubtotal(?float $amount): self;

    /** @return float|null */
    public function getMaxCartSubtotal(): ?float;

    /** @return self */
    public function setMaxCartSubtotal(?float $amount): self;

    /** @return string */
    public function getStoreIds(): string;

    /** @return self */
    public function setStoreIds(string $storeIds): self;

    /** @return string */
    public function getCustomerGroupIds(): string;

    /** @return self */
    public function setCustomerGroupIds(string $groupIds): self;

    /** @return string */
    public function getPopupHeadline(): string;

    /** @return self */
    public function setPopupHeadline(string $headline): self;

    /** @return string|null */
    public function getPopupBody(): ?string;

    /** @return self */
    public function setPopupBody(?string $body): self;

    /** @return string */
    public function getPopupCtaText(): string;

    /** @return self */
    public function setPopupCtaText(string $cta): self;

    /** @return string|null */
    public function getPopupImageUrl(): ?string;

    /** @return self */
    public function setPopupImageUrl(?string $url): self;

    /** @return int|null */
    public function getSalesRuleId(): ?int;

    /** @return self */
    public function setSalesRuleId(?int $ruleId): self;

    /** @return string */
    public function getFrequency(): string;

    /** @return self */
    public function setFrequency(string $frequency): self;

    /** @return int */
    public function getMaxImpressionsPerCustomer(): int;

    /** @return self */
    public function setMaxImpressionsPerCustomer(int $max): self;

    /** @return bool */
    public function isApplyToGuests(): bool;

    /** @return self */
    public function setApplyToGuests(bool $applyToGuests): self;

    /* ===== Visual design (v1.2.0) ===== */

    /** @return string */
    public function getTemplateLayout(): string;

    /** @return self */
    public function setTemplateLayout(string $layout): self;

    /** @return string */
    public function getBgColor(): string;

    /** @return self */
    public function setBgColor(string $hex): self;

    /** @return string */
    public function getHeadlineColor(): string;

    /** @return self */
    public function setHeadlineColor(string $hex): self;

    /** @return string */
    public function getBodyColor(): string;

    /** @return self */
    public function setBodyColor(string $hex): self;

    /** @return string */
    public function getCtaBgColor(): string;

    /** @return self */
    public function setCtaBgColor(string $hex): self;

    /** @return string */
    public function getCtaTextColor(): string;

    /** @return self */
    public function setCtaTextColor(string $hex): self;

    /** @return int */
    public function getBorderRadius(): int;

    /** @return self */
    public function setBorderRadius(int $px): self;

    /** @return int */
    public function getDialogWidth(): int;

    /** @return self */
    public function setDialogWidth(int $px): self;

    /** @return string */
    public function getAnimationType(): string;

    /** @return self */
    public function setAnimationType(string $animation): self;

    /* ===== Mobile (v1.2.0) ===== */

    /** @return int */
    public function getMobileFallbackSeconds(): int;

    /** @return self */
    public function setMobileFallbackSeconds(int $seconds): self;

    /** @return string */
    public function getCreatedAt(): string;

    /** @return self */
    public function setCreatedAt(string $timestamp): self;

    /** @return string */
    public function getUpdatedAt(): string;

    /** @return self */
    public function setUpdatedAt(string $timestamp): self;
}
