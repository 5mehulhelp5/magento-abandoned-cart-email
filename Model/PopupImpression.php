<?php
/**
 * Etechflow_AbandonedCart - PopupImpression model.
 *
 * Implements [[Etechflow\AbandonedCart\Api\Data\PopupImpressionInterface]].
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\PopupImpressionInterface;
use Etechflow\AbandonedCart\Model\ResourceModel\PopupImpression as PopupImpressionResource;
use Magento\Framework\Model\AbstractModel;

class PopupImpression extends AbstractModel implements PopupImpressionInterface
{
    protected $_eventPrefix = 'etechflow_popup_impression';
    protected $_eventObject = 'popup_impression';

    protected function _construct(): void
    {
        $this->_init(PopupImpressionResource::class);
    }

    /**
     * Required for UI Component DataProvider compat (admin grid).
     *
     * @return \Magento\Framework\Api\AttributeValue[]
     */
    public function getCustomAttributes(): array
    {
        $attributes = [];
        foreach ($this->getData() as $code => $value) {
            $attr = new \Magento\Framework\Api\AttributeValue();
            $attr->setAttributeCode((string) $code);
            $attr->setValue($value);
            $attributes[] = $attr;
        }
        return $attributes;
    }

    public function getImpressionId(): ?int
    {
        $value = $this->getData(self::IMPRESSION_ID);
        return $value === null ? null : (int) $value;
    }

    /**
     * LSP-safe untyped param (matches AbstractModel::setId()).
     */
    public function setImpressionId($id): self
    {
        return $this->setData(self::IMPRESSION_ID, (int) $id);
    }

    public function getPopupRuleId(): int
    {
        return (int) $this->getData(self::POPUP_RULE_ID);
    }

    public function setPopupRuleId(int $ruleId): self
    {
        return $this->setData(self::POPUP_RULE_ID, $ruleId);
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

    public function getCustomerEmail(): ?string
    {
        $value = $this->getData(self::CUSTOMER_EMAIL);
        return $value === null ? null : (string) $value;
    }

    public function setCustomerEmail(?string $email): self
    {
        return $this->setData(self::CUSTOMER_EMAIL, $email);
    }

    public function getQuoteId(): ?int
    {
        $value = $this->getData(self::QUOTE_ID);
        return $value === null ? null : (int) $value;
    }

    public function setQuoteId(?int $quoteId): self
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    public function getSessionId(): string
    {
        return (string) $this->getData(self::SESSION_ID);
    }

    public function setSessionId(string $sessionId): self
    {
        return $this->setData(self::SESSION_ID, $sessionId);
    }

    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getDeviceType(): string
    {
        return (string) $this->getData(self::DEVICE_TYPE);
    }

    public function setDeviceType(string $deviceType): self
    {
        return $this->setData(self::DEVICE_TYPE, $deviceType);
    }

    public function getShownAt(): string
    {
        return (string) $this->getData(self::SHOWN_AT);
    }

    public function setShownAt(string $timestamp): self
    {
        return $this->setData(self::SHOWN_AT, $timestamp);
    }

    public function getDismissedAt(): ?string
    {
        $value = $this->getData(self::DISMISSED_AT);
        return $value === null ? null : (string) $value;
    }

    public function setDismissedAt(?string $timestamp): self
    {
        return $this->setData(self::DISMISSED_AT, $timestamp);
    }

    public function getAcceptedAt(): ?string
    {
        $value = $this->getData(self::ACCEPTED_AT);
        return $value === null ? null : (string) $value;
    }

    public function setAcceptedAt(?string $timestamp): self
    {
        return $this->setData(self::ACCEPTED_AT, $timestamp);
    }

    public function getCouponCodeGenerated(): ?string
    {
        $value = $this->getData(self::COUPON_CODE_GENERATED);
        return $value === null ? null : (string) $value;
    }

    public function setCouponCodeGenerated(?string $code): self
    {
        return $this->setData(self::COUPON_CODE_GENERATED, $code);
    }

    public function getConvertedOrderId(): ?int
    {
        $value = $this->getData(self::CONVERTED_ORDER_ID);
        return $value === null ? null : (int) $value;
    }

    public function setConvertedOrderId(?int $orderId): self
    {
        return $this->setData(self::CONVERTED_ORDER_ID, $orderId);
    }
}
