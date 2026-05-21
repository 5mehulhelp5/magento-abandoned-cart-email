<?php
/**
 * Etechflow_AbandonedCart - AbandonedCart data interface.
 *
 * Service contract per ETechFlow Module Development Standards §7. Every
 * method MUST have a docblock — Magento's webapi/extension generator walks
 * each one and the absence of a docblock throws InvalidArgumentException on
 * the first product save after setup:di:compile. Don't skip them.
 *
 * Column constants mirror etc/db_schema.xml `etechflow_abandoned_cart` table.
 * Status enum values mirror the comment on the `status` column in db_schema.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Api\Data;

interface AbandonedCartInterface
{
    public const ENTITY_ID           = 'entity_id';
    public const QUOTE_ID            = 'quote_id';
    public const STORE_ID            = 'store_id';
    public const CUSTOMER_ID         = 'customer_id';
    public const CUSTOMER_EMAIL      = 'customer_email';
    public const CUSTOMER_FIRSTNAME  = 'customer_firstname';
    public const CUSTOMER_LASTNAME   = 'customer_lastname';
    public const CUSTOMER_GROUP_ID   = 'customer_group_id';
    public const ITEMS_COUNT         = 'items_count';
    public const ITEMS_QTY           = 'items_qty';
    public const SUBTOTAL            = 'subtotal';
    public const GRAND_TOTAL         = 'grand_total';
    public const CURRENCY_CODE       = 'currency_code';
    public const STATUS              = 'status';
    public const RESTORE_TOKEN       = 'restore_token';
    public const EMAILS_SENT         = 'emails_sent';
    public const LAST_EMAIL_SENT_AT  = 'last_email_sent_at';
    public const RECOVERED_ORDER_ID  = 'recovered_order_id';
    public const RECOVERED_REVENUE   = 'recovered_revenue';
    public const ABANDONED_AT        = 'abandoned_at';
    public const RECOVERED_AT        = 'recovered_at';
    public const CREATED_AT          = 'created_at';
    public const UPDATED_AT          = 'updated_at';

    public const STATUS_PENDING      = 1;
    public const STATUS_PROCESSING   = 2;
    public const STATUS_RECOVERED    = 3;
    public const STATUS_EXPIRED      = 4;
    public const STATUS_UNSUBSCRIBED = 5;

    /** @return int|null */
    public function getEntityId(): ?int;

    /**
     * Param is untyped to stay compatible with Magento\Framework\Model\AbstractModel::setEntityId($entityId),
     * which has no type hint. PHP 8 LSP rejects narrowing the parameter type.
     *
     * @param int $id
     * @return self
     */
    public function setEntityId($id): self;

    /** @return int */
    public function getQuoteId(): int;

    /** @return self */
    public function setQuoteId(int $quoteId): self;

    /** @return int */
    public function getStoreId(): int;

    /** @return self */
    public function setStoreId(int $storeId): self;

    /** @return int|null */
    public function getCustomerId(): ?int;

    /** @return self */
    public function setCustomerId(?int $customerId): self;

    /** @return string */
    public function getCustomerEmail(): string;

    /** @return self */
    public function setCustomerEmail(string $email): self;

    /** @return string|null */
    public function getCustomerFirstname(): ?string;

    /** @return self */
    public function setCustomerFirstname(?string $firstname): self;

    /** @return string|null */
    public function getCustomerLastname(): ?string;

    /** @return self */
    public function setCustomerLastname(?string $lastname): self;

    /** @return int */
    public function getCustomerGroupId(): int;

    /** @return self */
    public function setCustomerGroupId(int $groupId): self;

    /** @return int */
    public function getItemsCount(): int;

    /** @return self */
    public function setItemsCount(int $count): self;

    /** @return int */
    public function getItemsQty(): int;

    /** @return self */
    public function setItemsQty(int $qty): self;

    /** @return float */
    public function getSubtotal(): float;

    /** @return self */
    public function setSubtotal(float $subtotal): self;

    /** @return float */
    public function getGrandTotal(): float;

    /** @return self */
    public function setGrandTotal(float $grandTotal): self;

    /** @return string */
    public function getCurrencyCode(): string;

    /** @return self */
    public function setCurrencyCode(string $currencyCode): self;

    /** @return int */
    public function getStatus(): int;

    /** @return self */
    public function setStatus(int $status): self;

    /** @return string */
    public function getRestoreToken(): string;

    /** @return self */
    public function setRestoreToken(string $token): self;

    /** @return int */
    public function getEmailsSent(): int;

    /** @return self */
    public function setEmailsSent(int $count): self;

    /** @return string|null */
    public function getLastEmailSentAt(): ?string;

    /** @return self */
    public function setLastEmailSentAt(?string $timestamp): self;

    /** @return int|null */
    public function getRecoveredOrderId(): ?int;

    /** @return self */
    public function setRecoveredOrderId(?int $orderId): self;

    /** @return float|null */
    public function getRecoveredRevenue(): ?float;

    /** @return self */
    public function setRecoveredRevenue(?float $revenue): self;

    /** @return string */
    public function getAbandonedAt(): string;

    /** @return self */
    public function setAbandonedAt(string $timestamp): self;

    /** @return string|null */
    public function getRecoveredAt(): ?string;

    /** @return self */
    public function setRecoveredAt(?string $timestamp): self;

    /** @return string */
    public function getCreatedAt(): string;

    /** @return self */
    public function setCreatedAt(string $timestamp): self;

    /** @return string */
    public function getUpdatedAt(): string;

    /** @return self */
    public function setUpdatedAt(string $timestamp): self;
}
