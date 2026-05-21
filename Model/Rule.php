<?php
/**
 * Etechflow_AbandonedCart - Rule model.
 *
 * Implements [[Etechflow\AbandonedCart\Api\Data\RuleInterface]].
 *
 * Boolean fields (is_active, apply_to_guests) are stored as smallint 0/1 in
 * DB — the interface exposes them as `bool`, the setter casts back to int.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\RuleInterface;
use Etechflow\AbandonedCart\Model\ResourceModel\Rule as RuleResource;
use Magento\Framework\Model\AbstractModel;

class Rule extends AbstractModel implements RuleInterface
{
    protected $_eventPrefix = 'etechflow_abandoned_cart_rule';
    protected $_eventObject = 'rule';

    protected function _construct(): void
    {
        $this->_init(RuleResource::class);
    }

    public function getRuleId(): ?int
    {
        $value = $this->getData(self::RULE_ID);
        return $value === null ? null : (int) $value;
    }

    public function setRuleId(int $id): self
    {
        return $this->setData(self::RULE_ID, $id);
    }

    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData(self::DESCRIPTION);
        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $description): self
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    public function getStoreIds(): string
    {
        return (string) $this->getData(self::STORE_IDS);
    }

    public function setStoreIds(string $storeIds): self
    {
        return $this->setData(self::STORE_IDS, $storeIds);
    }

    public function getCustomerGroupIds(): string
    {
        return (string) $this->getData(self::CUSTOMER_GROUP_IDS);
    }

    public function setCustomerGroupIds(string $groupIds): self
    {
        return $this->setData(self::CUSTOMER_GROUP_IDS, $groupIds);
    }

    public function getSendAfterMinutes(): int
    {
        return (int) $this->getData(self::SEND_AFTER_MINUTES);
    }

    public function setSendAfterMinutes(int $minutes): self
    {
        return $this->setData(self::SEND_AFTER_MINUTES, $minutes);
    }

    public function getSequenceNumber(): int
    {
        return (int) $this->getData(self::SEQUENCE_NUMBER);
    }

    public function setSequenceNumber(int $sequence): self
    {
        return $this->setData(self::SEQUENCE_NUMBER, $sequence);
    }

    public function getEmailTemplate(): string
    {
        return (string) $this->getData(self::EMAIL_TEMPLATE);
    }

    public function setEmailTemplate(string $template): self
    {
        return $this->setData(self::EMAIL_TEMPLATE, $template);
    }

    public function getEmailSender(): string
    {
        return (string) $this->getData(self::EMAIL_SENDER);
    }

    public function setEmailSender(string $sender): self
    {
        return $this->setData(self::EMAIL_SENDER, $sender);
    }

    public function getMinCartSubtotal(): ?float
    {
        $value = $this->getData(self::MIN_CART_SUBTOTAL);
        return $value === null ? null : (float) $value;
    }

    public function setMinCartSubtotal(?float $amount): self
    {
        return $this->setData(self::MIN_CART_SUBTOTAL, $amount);
    }

    public function getMaxCartSubtotal(): ?float
    {
        $value = $this->getData(self::MAX_CART_SUBTOTAL);
        return $value === null ? null : (float) $value;
    }

    public function setMaxCartSubtotal(?float $amount): self
    {
        return $this->setData(self::MAX_CART_SUBTOTAL, $amount);
    }

    public function getConditionsSerialized(): ?string
    {
        $value = $this->getData(self::CONDITIONS_SERIALIZED);
        return $value === null ? null : (string) $value;
    }

    public function setConditionsSerialized(?string $conditions): self
    {
        return $this->setData(self::CONDITIONS_SERIALIZED, $conditions);
    }

    public function isApplyToGuests(): bool
    {
        return (bool) $this->getData(self::APPLY_TO_GUESTS);
    }

    public function setApplyToGuests(bool $applyToGuests): self
    {
        return $this->setData(self::APPLY_TO_GUESTS, $applyToGuests ? 1 : 0);
    }

    public function getPriority(): int
    {
        return (int) $this->getData(self::PRIORITY);
    }

    public function setPriority(int $priority): self
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    public function getCreatedAt(): string
    {
        return (string) $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $timestamp): self
    {
        return $this->setData(self::CREATED_AT, $timestamp);
    }

    public function getUpdatedAt(): string
    {
        return (string) $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(string $timestamp): self
    {
        return $this->setData(self::UPDATED_AT, $timestamp);
    }
}
