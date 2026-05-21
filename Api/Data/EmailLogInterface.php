<?php
/**
 * Etechflow_AbandonedCart - EmailLog data interface.
 *
 * Service contract for per-email delivery records. One row per reminder
 * sent (or attempted). Drives the recovery dashboard's open/click/conversion
 * metrics and provides the per-cart email history shown in the admin
 * cart-view page.
 *
 * Column constants mirror etc/db_schema.xml `etechflow_abandoned_cart_email_log`.
 * Status enum values mirror the comment on the `status` column in db_schema.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Api\Data;

interface EmailLogInterface
{
    public const LOG_ID             = 'log_id';
    public const CART_ID            = 'cart_id';
    public const RULE_ID            = 'rule_id';
    public const RECIPIENT_EMAIL    = 'recipient_email';
    public const EMAIL_TEMPLATE     = 'email_template';
    public const SEQUENCE_NUMBER    = 'sequence_number';
    public const STATUS             = 'status';
    public const ERROR_MESSAGE      = 'error_message';
    public const SENT_AT            = 'sent_at';
    public const OPENED_AT          = 'opened_at';
    public const CLICKED_AT         = 'clicked_at';
    public const OPEN_COUNT         = 'open_count';
    public const CLICK_COUNT        = 'click_count';
    public const RECOVERED_ORDER_ID = 'recovered_order_id';
    public const CREATED_AT         = 'created_at';

    public const STATUS_QUEUED    = 1;
    public const STATUS_SENT      = 2;
    public const STATUS_FAILED    = 3;
    public const STATUS_OPENED    = 4;
    public const STATUS_CLICKED   = 5;
    public const STATUS_CONVERTED = 6;

    /** @return int|null */
    public function getLogId(): ?int;

    /** @return self */
    public function setLogId(int $id): self;

    /** @return int */
    public function getCartId(): int;

    /** @return self */
    public function setCartId(int $cartId): self;

    /** @return int|null */
    public function getRuleId(): ?int;

    /** @return self */
    public function setRuleId(?int $ruleId): self;

    /** @return string */
    public function getRecipientEmail(): string;

    /** @return self */
    public function setRecipientEmail(string $email): self;

    /** @return string */
    public function getEmailTemplate(): string;

    /** @return self */
    public function setEmailTemplate(string $template): self;

    /** @return int */
    public function getSequenceNumber(): int;

    /** @return self */
    public function setSequenceNumber(int $sequence): self;

    /** @return int */
    public function getStatus(): int;

    /** @return self */
    public function setStatus(int $status): self;

    /** @return string|null */
    public function getErrorMessage(): ?string;

    /** @return self */
    public function setErrorMessage(?string $message): self;

    /** @return string|null */
    public function getSentAt(): ?string;

    /** @return self */
    public function setSentAt(?string $timestamp): self;

    /** @return string|null */
    public function getOpenedAt(): ?string;

    /** @return self */
    public function setOpenedAt(?string $timestamp): self;

    /** @return string|null */
    public function getClickedAt(): ?string;

    /** @return self */
    public function setClickedAt(?string $timestamp): self;

    /** @return int */
    public function getOpenCount(): int;

    /** @return self */
    public function setOpenCount(int $count): self;

    /** @return int */
    public function getClickCount(): int;

    /** @return self */
    public function setClickCount(int $count): self;

    /** @return int|null */
    public function getRecoveredOrderId(): ?int;

    /** @return self */
    public function setRecoveredOrderId(?int $orderId): self;

    /** @return string */
    public function getCreatedAt(): string;

    /** @return self */
    public function setCreatedAt(string $timestamp): self;
}
