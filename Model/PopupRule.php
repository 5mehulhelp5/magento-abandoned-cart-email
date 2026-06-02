<?php
/**
 * Etechflow_AbandonedCart - PopupRule model.
 *
 * Implements [[Etechflow\AbandonedCart\Api\Data\PopupRuleInterface]].
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Etechflow\AbandonedCart\Model\ResourceModel\PopupRule as PopupRuleResource;
use Magento\Framework\Model\AbstractModel;

class PopupRule extends AbstractModel implements PopupRuleInterface
{
    protected $_eventPrefix = 'etechflow_popup_rule';
    protected $_eventObject = 'popup_rule';

    protected function _construct(): void
    {
        $this->_init(PopupRuleResource::class);
    }

    /**
     * Required for Magento UI Component DataProvider compatibility (admin grid).
     * Without this, searchResultToOutput crashes with foreach over null.
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

    public function getRuleId(): ?int
    {
        $value = $this->getData(self::RULE_ID);
        return $value === null ? null : (int) $value;
    }

    /**
     * LSP-safe untyped param (matches AbstractModel::setId()).
     */
    public function setRuleId($id): self
    {
        return $this->setData(self::RULE_ID, (int) $id);
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

    public function getPriority(): int
    {
        return (int) $this->getData(self::PRIORITY);
    }

    public function setPriority(int $priority): self
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    public function getTriggerType(): string
    {
        return (string) $this->getData(self::TRIGGER_TYPE);
    }

    public function setTriggerType(string $triggerType): self
    {
        return $this->setData(self::TRIGGER_TYPE, $triggerType);
    }

    public function getTriggerValue(): ?int
    {
        $value = $this->getData(self::TRIGGER_VALUE);
        return $value === null ? null : (int) $value;
    }

    public function setTriggerValue(?int $value): self
    {
        return $this->setData(self::TRIGGER_VALUE, $value);
    }

    public function getPageScope(): string
    {
        return (string) $this->getData(self::PAGE_SCOPE);
    }

    public function setPageScope(string $scope): self
    {
        return $this->setData(self::PAGE_SCOPE, $scope);
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

    public function getPopupHeadline(): string
    {
        return (string) $this->getData(self::POPUP_HEADLINE);
    }

    public function setPopupHeadline(string $headline): self
    {
        return $this->setData(self::POPUP_HEADLINE, $headline);
    }

    public function getPopupBody(): ?string
    {
        $value = $this->getData(self::POPUP_BODY);
        return $value === null ? null : (string) $value;
    }

    public function setPopupBody(?string $body): self
    {
        return $this->setData(self::POPUP_BODY, $body);
    }

    public function getPopupCtaText(): string
    {
        return (string) $this->getData(self::POPUP_CTA_TEXT);
    }

    public function setPopupCtaText(string $cta): self
    {
        return $this->setData(self::POPUP_CTA_TEXT, $cta);
    }

    public function getPopupImageUrl(): ?string
    {
        $value = $this->getData(self::POPUP_IMAGE_URL);
        return $value === null ? null : (string) $value;
    }

    public function setPopupImageUrl(?string $url): self
    {
        return $this->setData(self::POPUP_IMAGE_URL, $url);
    }

    public function getSalesRuleId(): ?int
    {
        $value = $this->getData(self::SALES_RULE_ID);
        return $value === null ? null : (int) $value;
    }

    public function setSalesRuleId(?int $ruleId): self
    {
        return $this->setData(self::SALES_RULE_ID, $ruleId);
    }

    public function getFrequency(): string
    {
        return (string) $this->getData(self::FREQUENCY);
    }

    public function setFrequency(string $frequency): self
    {
        return $this->setData(self::FREQUENCY, $frequency);
    }

    public function getMaxImpressionsPerCustomer(): int
    {
        return (int) $this->getData(self::MAX_IMPRESSIONS_PER_CUSTOMER);
    }

    public function setMaxImpressionsPerCustomer(int $max): self
    {
        return $this->setData(self::MAX_IMPRESSIONS_PER_CUSTOMER, $max);
    }

    public function isApplyToGuests(): bool
    {
        return (bool) $this->getData(self::APPLY_TO_GUESTS);
    }

    public function setApplyToGuests(bool $applyToGuests): self
    {
        return $this->setData(self::APPLY_TO_GUESTS, $applyToGuests ? 1 : 0);
    }

    /* ===== Visual design (v1.2.0) ===== */

    public function getTemplateLayout(): string
    {
        $value = $this->getData(self::TEMPLATE_LAYOUT);
        return $value !== null && $value !== '' ? (string) $value : self::LAYOUT_MODAL;
    }

    public function setTemplateLayout(string $layout): self
    {
        return $this->setData(self::TEMPLATE_LAYOUT, $layout);
    }

    public function getBgColor(): string
    {
        $value = $this->getData(self::BG_COLOR);
        return $value !== null && $value !== '' ? (string) $value : '#ffffff';
    }

    public function setBgColor(string $hex): self
    {
        return $this->setData(self::BG_COLOR, $hex);
    }

    public function getHeadlineColor(): string
    {
        $value = $this->getData(self::HEADLINE_COLOR);
        return $value !== null && $value !== '' ? (string) $value : '#0f172a';
    }

    public function setHeadlineColor(string $hex): self
    {
        return $this->setData(self::HEADLINE_COLOR, $hex);
    }

    public function getBodyColor(): string
    {
        $value = $this->getData(self::BODY_COLOR);
        return $value !== null && $value !== '' ? (string) $value : '#374151';
    }

    public function setBodyColor(string $hex): self
    {
        return $this->setData(self::BODY_COLOR, $hex);
    }

    public function getCtaBgColor(): string
    {
        $value = $this->getData(self::CTA_BG_COLOR);
        return $value !== null && $value !== '' ? (string) $value : '#0f172a';
    }

    public function setCtaBgColor(string $hex): self
    {
        return $this->setData(self::CTA_BG_COLOR, $hex);
    }

    public function getCtaTextColor(): string
    {
        $value = $this->getData(self::CTA_TEXT_COLOR);
        return $value !== null && $value !== '' ? (string) $value : '#ffffff';
    }

    public function setCtaTextColor(string $hex): self
    {
        return $this->setData(self::CTA_TEXT_COLOR, $hex);
    }

    public function getBorderRadius(): int
    {
        $value = $this->getData(self::BORDER_RADIUS);
        return $value !== null ? (int) $value : 12;
    }

    public function setBorderRadius(int $px): self
    {
        return $this->setData(self::BORDER_RADIUS, $px);
    }

    public function getDialogWidth(): int
    {
        $value = $this->getData(self::DIALOG_WIDTH);
        return $value !== null ? (int) $value : 480;
    }

    public function setDialogWidth(int $px): self
    {
        return $this->setData(self::DIALOG_WIDTH, $px);
    }

    public function getAnimationType(): string
    {
        $value = $this->getData(self::ANIMATION_TYPE);
        return $value !== null && $value !== '' ? (string) $value : self::ANIMATION_ZOOM_IN;
    }

    public function setAnimationType(string $animation): self
    {
        return $this->setData(self::ANIMATION_TYPE, $animation);
    }

    /* ===== Mobile (v1.2.0) ===== */

    public function getMobileFallbackSeconds(): int
    {
        $value = $this->getData(self::MOBILE_FALLBACK_SECONDS);
        return $value !== null ? (int) $value : 15;
    }

    public function setMobileFallbackSeconds(int $seconds): self
    {
        return $this->setData(self::MOBILE_FALLBACK_SECONDS, $seconds);
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
