<?php
/**
 * Etechflow_AbandonedCart - AbandonedCart model.
 *
 * Implements [[Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface]] and
 * extends Magento's AbstractModel. The interface lives in Api/ for service-
 * contract stability; this concrete class is what DI's `<preference>` in
 * etc/di.xml binds the interface to.
 *
 * Typed getters cast every read out of the AbstractModel data array — the
 * interface declares `int`/`string`/`float` returns, but AbstractModel stores
 * everything as MySQL string by default. Without the casts a strict-typed
 * caller would TypeError.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Model\ResourceModel\AbandonedCart as AbandonedCartResource;
use Magento\Framework\Model\AbstractModel;

class AbandonedCart extends AbstractModel implements AbandonedCartInterface
{
    protected $_eventPrefix = 'etechflow_abandoned_cart';
    protected $_eventObject = 'abandoned_cart';

    protected function _construct(): void
    {
        $this->_init(AbandonedCartResource::class);
    }

    public function getEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);
        return $value === null ? null : (int) $value;
    }

    /**
     * Param intentionally untyped to satisfy LSP with Magento\Framework\Model\AbstractModel::setEntityId().
     * Casting happens at the storage edge.
     */
    public function setEntityId($id): self
    {
        return $this->setData(self::ENTITY_ID, (int) $id);
    }

    public function getQuoteId(): int
    {
        return (int) $this->getData(self::QUOTE_ID);
    }

    public function setQuoteId(int $quoteId): self
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData(self::CUSTOMER_ID);
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $customerId): self
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    public function getCustomerEmail(): string
    {
        return (string) $this->getData(self::CUSTOMER_EMAIL);
    }

    public function setCustomerEmail(string $email): self
    {
        return $this->setData(self::CUSTOMER_EMAIL, $email);
    }

    public function getCustomerFirstname(): ?string
    {
        $value = $this->getData(self::CUSTOMER_FIRSTNAME);
        return $value === null ? null : (string) $value;
    }

    public function setCustomerFirstname(?string $firstname): self
    {
        return $this->setData(self::CUSTOMER_FIRSTNAME, $firstname);
    }

    public function getCustomerLastname(): ?string
    {
        $value = $this->getData(self::CUSTOMER_LASTNAME);
        return $value === null ? null : (string) $value;
    }

    public function setCustomerLastname(?string $lastname): self
    {
        return $this->setData(self::CUSTOMER_LASTNAME, $lastname);
    }

    public function getCustomerGroupId(): int
    {
        return (int) $this->getData(self::CUSTOMER_GROUP_ID);
    }

    public function setCustomerGroupId(int $groupId): self
    {
        return $this->setData(self::CUSTOMER_GROUP_ID, $groupId);
    }

    public function getItemsCount(): int
    {
        return (int) $this->getData(self::ITEMS_COUNT);
    }

    public function setItemsCount(int $count): self
    {
        return $this->setData(self::ITEMS_COUNT, $count);
    }

    public function getItemsQty(): int
    {
        return (int) $this->getData(self::ITEMS_QTY);
    }

    public function setItemsQty(int $qty): self
    {
        return $this->setData(self::ITEMS_QTY, $qty);
    }

    public function getSubtotal(): float
    {
        return (float) $this->getData(self::SUBTOTAL);
    }

    public function setSubtotal(float $subtotal): self
    {
        return $this->setData(self::SUBTOTAL, $subtotal);
    }

    public function getGrandTotal(): float
    {
        return (float) $this->getData(self::GRAND_TOTAL);
    }

    public function setGrandTotal(float $grandTotal): self
    {
        return $this->setData(self::GRAND_TOTAL, $grandTotal);
    }

    public function getCurrencyCode(): string
    {
        return (string) $this->getData(self::CURRENCY_CODE);
    }

    public function setCurrencyCode(string $currencyCode): self
    {
        return $this->setData(self::CURRENCY_CODE, $currencyCode);
    }

    public function getStatus(): int
    {
        return (int) $this->getData(self::STATUS);
    }

    public function setStatus(int $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getRestoreToken(): string
    {
        return (string) $this->getData(self::RESTORE_TOKEN);
    }

    public function setRestoreToken(string $token): self
    {
        return $this->setData(self::RESTORE_TOKEN, $token);
    }

    public function getEmailsSent(): int
    {
        return (int) $this->getData(self::EMAILS_SENT);
    }

    public function setEmailsSent(int $count): self
    {
        return $this->setData(self::EMAILS_SENT, $count);
    }

    public function getLastEmailSentAt(): ?string
    {
        $value = $this->getData(self::LAST_EMAIL_SENT_AT);
        return $value === null ? null : (string) $value;
    }

    public function setLastEmailSentAt(?string $timestamp): self
    {
        return $this->setData(self::LAST_EMAIL_SENT_AT, $timestamp);
    }

    public function getRecoveredOrderId(): ?int
    {
        $value = $this->getData(self::RECOVERED_ORDER_ID);
        return $value === null ? null : (int) $value;
    }

    public function setRecoveredOrderId(?int $orderId): self
    {
        return $this->setData(self::RECOVERED_ORDER_ID, $orderId);
    }

    public function getRecoveredRevenue(): ?float
    {
        $value = $this->getData(self::RECOVERED_REVENUE);
        return $value === null ? null : (float) $value;
    }

    public function setRecoveredRevenue(?float $revenue): self
    {
        return $this->setData(self::RECOVERED_REVENUE, $revenue);
    }

    public function getAbandonedAt(): string
    {
        return (string) $this->getData(self::ABANDONED_AT);
    }

    public function setAbandonedAt(string $timestamp): self
    {
        return $this->setData(self::ABANDONED_AT, $timestamp);
    }

    public function getRecoveredAt(): ?string
    {
        $value = $this->getData(self::RECOVERED_AT);
        return $value === null ? null : (string) $value;
    }

    public function setRecoveredAt(?string $timestamp): self
    {
        return $this->setData(self::RECOVERED_AT, $timestamp);
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
