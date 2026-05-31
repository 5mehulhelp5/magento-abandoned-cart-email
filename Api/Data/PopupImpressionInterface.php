<?php
/**
 * Etechflow_AbandonedCart - PopupImpression data interface.
 *
 * One row per popup display event. shown_at fires on render, dismissed_at
 * on customer close, accepted_at on CTA click. converted_order_id is set
 * later by the order-place observer when the popup's coupon code is used.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Api\Data;

interface PopupImpressionInterface
{
    public const IMPRESSION_ID            = 'impression_id';
    public const POPUP_RULE_ID            = 'popup_rule_id';
    public const CUSTOMER_ID              = 'customer_id';
    public const CUSTOMER_EMAIL           = 'customer_email';
    public const QUOTE_ID                 = 'quote_id';
    public const SESSION_ID               = 'session_id';
    public const STORE_ID                 = 'store_id';
    public const DEVICE_TYPE              = 'device_type';
    public const SHOWN_AT                 = 'shown_at';
    public const DISMISSED_AT             = 'dismissed_at';
    public const ACCEPTED_AT              = 'accepted_at';
    public const COUPON_CODE_GENERATED    = 'coupon_code_generated';
    public const CONVERTED_ORDER_ID       = 'converted_order_id';

    public const DEVICE_DESKTOP           = 'desktop';
    public const DEVICE_MOBILE            = 'mobile';
    public const DEVICE_TABLET            = 'tablet';

    /** @return int|null */
    public function getImpressionId(): ?int;

    /**
     * Untyped param to satisfy LSP with AbstractModel::setId().
     * @param int $id
     * @return self
     */
    public function setImpressionId($id): self;

    /** @return int */
    public function getPopupRuleId(): int;

    /** @return self */
    public function setPopupRuleId(int $ruleId): self;

    /** @return int|null */
    public function getCustomerId(): ?int;

    /** @return self */
    public function setCustomerId(?int $customerId): self;

    /** @return string|null */
    public function getCustomerEmail(): ?string;

    /** @return self */
    public function setCustomerEmail(?string $email): self;

    /** @return int|null */
    public function getQuoteId(): ?int;

    /** @return self */
    public function setQuoteId(?int $quoteId): self;

    /** @return string */
    public function getSessionId(): string;

    /** @return self */
    public function setSessionId(string $sessionId): self;

    /** @return int */
    public function getStoreId(): int;

    /** @return self */
    public function setStoreId(int $storeId): self;

    /** @return string */
    public function getDeviceType(): string;

    /** @return self */
    public function setDeviceType(string $deviceType): self;

    /** @return string */
    public function getShownAt(): string;

    /** @return self */
    public function setShownAt(string $timestamp): self;

    /** @return string|null */
    public function getDismissedAt(): ?string;

    /** @return self */
    public function setDismissedAt(?string $timestamp): self;

    /** @return string|null */
    public function getAcceptedAt(): ?string;

    /** @return self */
    public function setAcceptedAt(?string $timestamp): self;

    /** @return string|null */
    public function getCouponCodeGenerated(): ?string;

    /** @return self */
    public function setCouponCodeGenerated(?string $code): self;

    /** @return int|null */
    public function getConvertedOrderId(): ?int;

    /** @return self */
    public function setConvertedOrderId(?int $orderId): self;
}
