<?php
/**
 * Etechflow_AbandonedCart - Rule data interface.
 *
 * Service contract for the admin-managed email rules. Each rule represents
 * one reminder in a sequence (e.g., "first email at 1h", "second at 24h",
 * "third at 72h") and carries its own conditions + email template binding.
 *
 * Column constants mirror etc/db_schema.xml `etechflow_abandoned_cart_rule`.
 * store_ids / customer_group_ids are CSV strings in DB ("0" = all). Helper
 * methods to explode them belong on the concrete Model in Phase 7.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Api\Data;

interface RuleInterface
{
    public const RULE_ID               = 'rule_id';
    public const NAME                  = 'name';
    public const DESCRIPTION           = 'description';
    public const IS_ACTIVE             = 'is_active';
    public const STORE_IDS             = 'store_ids';
    public const CUSTOMER_GROUP_IDS    = 'customer_group_ids';
    public const SEND_AFTER_MINUTES    = 'send_after_minutes';
    public const SEQUENCE_NUMBER       = 'sequence_number';
    public const EMAIL_TEMPLATE        = 'email_template';
    public const EMAIL_SENDER          = 'email_sender';
    public const MIN_CART_SUBTOTAL     = 'min_cart_subtotal';
    public const MAX_CART_SUBTOTAL     = 'max_cart_subtotal';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const APPLY_TO_GUESTS       = 'apply_to_guests';
    public const PRIORITY              = 'priority';
    public const CREATED_AT            = 'created_at';
    public const UPDATED_AT            = 'updated_at';

    /** @return int|null */
    public function getRuleId(): ?int;

    /** @return self */
    public function setRuleId(int $id): self;

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

    /** @return string */
    public function getStoreIds(): string;

    /** @return self */
    public function setStoreIds(string $storeIds): self;

    /** @return string */
    public function getCustomerGroupIds(): string;

    /** @return self */
    public function setCustomerGroupIds(string $groupIds): self;

    /** @return int */
    public function getSendAfterMinutes(): int;

    /** @return self */
    public function setSendAfterMinutes(int $minutes): self;

    /** @return int */
    public function getSequenceNumber(): int;

    /** @return self */
    public function setSequenceNumber(int $sequence): self;

    /** @return string */
    public function getEmailTemplate(): string;

    /** @return self */
    public function setEmailTemplate(string $template): self;

    /** @return string */
    public function getEmailSender(): string;

    /** @return self */
    public function setEmailSender(string $sender): self;

    /** @return float|null */
    public function getMinCartSubtotal(): ?float;

    /** @return self */
    public function setMinCartSubtotal(?float $amount): self;

    /** @return float|null */
    public function getMaxCartSubtotal(): ?float;

    /** @return self */
    public function setMaxCartSubtotal(?float $amount): self;

    /** @return string|null */
    public function getConditionsSerialized(): ?string;

    /** @return self */
    public function setConditionsSerialized(?string $conditions): self;

    /** @return bool */
    public function isApplyToGuests(): bool;

    /** @return self */
    public function setApplyToGuests(bool $applyToGuests): self;

    /** @return int */
    public function getPriority(): int;

    /** @return self */
    public function setPriority(int $priority): self;

    /** @return string */
    public function getCreatedAt(): string;

    /** @return self */
    public function setCreatedAt(string $timestamp): self;

    /** @return string */
    public function getUpdatedAt(): string;

    /** @return self */
    public function setUpdatedAt(string $timestamp): self;
}
