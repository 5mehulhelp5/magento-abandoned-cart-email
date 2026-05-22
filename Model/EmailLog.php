<?php
/**
 * Etechflow_AbandonedCart - EmailLog model.
 *
 * Implements [[Etechflow\AbandonedCart\Api\Data\EmailLogInterface]].
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Model\ResourceModel\EmailLog as EmailLogResource;
use Magento\Framework\Model\AbstractModel;

class EmailLog extends AbstractModel implements EmailLogInterface
{
    protected $_eventPrefix = 'etechflow_abandoned_cart_email_log';
    protected $_eventObject = 'email_log';

    protected function _construct(): void
    {
        $this->_init(EmailLogResource::class);
    }

    /**
     * See AbandonedCart::getCustomAttributes() - same DataProvider compat shim.
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

    public function getLogId(): ?int
    {
        $value = $this->getData(self::LOG_ID);
        return $value === null ? null : (int) $value;
    }

    public function setLogId(int $id): self
    {
        return $this->setData(self::LOG_ID, $id);
    }

    public function getCartId(): int
    {
        return (int) $this->getData(self::CART_ID);
    }

    public function setCartId(int $cartId): self
    {
        return $this->setData(self::CART_ID, $cartId);
    }

    public function getRuleId(): ?int
    {
        $value = $this->getData(self::RULE_ID);
        return $value === null ? null : (int) $value;
    }

    public function setRuleId(?int $ruleId): self
    {
        return $this->setData(self::RULE_ID, $ruleId);
    }

    public function getRecipientEmail(): string
    {
        return (string) $this->getData(self::RECIPIENT_EMAIL);
    }

    public function setRecipientEmail(string $email): self
    {
        return $this->setData(self::RECIPIENT_EMAIL, $email);
    }

    public function getEmailTemplate(): string
    {
        return (string) $this->getData(self::EMAIL_TEMPLATE);
    }

    public function setEmailTemplate(string $template): self
    {
        return $this->setData(self::EMAIL_TEMPLATE, $template);
    }

    public function getSequenceNumber(): int
    {
        return (int) $this->getData(self::SEQUENCE_NUMBER);
    }

    public function setSequenceNumber(int $sequence): self
    {
        return $this->setData(self::SEQUENCE_NUMBER, $sequence);
    }

    public function getStatus(): int
    {
        return (int) $this->getData(self::STATUS);
    }

    public function setStatus(int $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getErrorMessage(): ?string
    {
        $value = $this->getData(self::ERROR_MESSAGE);
        return $value === null ? null : (string) $value;
    }

    public function setErrorMessage(?string $message): self
    {
        return $this->setData(self::ERROR_MESSAGE, $message);
    }

    public function getSentAt(): ?string
    {
        $value = $this->getData(self::SENT_AT);
        return $value === null ? null : (string) $value;
    }

    public function setSentAt(?string $timestamp): self
    {
        return $this->setData(self::SENT_AT, $timestamp);
    }

    public function getOpenedAt(): ?string
    {
        $value = $this->getData(self::OPENED_AT);
        return $value === null ? null : (string) $value;
    }

    public function setOpenedAt(?string $timestamp): self
    {
        return $this->setData(self::OPENED_AT, $timestamp);
    }

    public function getClickedAt(): ?string
    {
        $value = $this->getData(self::CLICKED_AT);
        return $value === null ? null : (string) $value;
    }

    public function setClickedAt(?string $timestamp): self
    {
        return $this->setData(self::CLICKED_AT, $timestamp);
    }

    public function getOpenCount(): int
    {
        return (int) $this->getData(self::OPEN_COUNT);
    }

    public function setOpenCount(int $count): self
    {
        return $this->setData(self::OPEN_COUNT, $count);
    }

    public function getClickCount(): int
    {
        return (int) $this->getData(self::CLICK_COUNT);
    }

    public function setClickCount(int $count): self
    {
        return $this->setData(self::CLICK_COUNT, $count);
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

    public function getCreatedAt(): string
    {
        return (string) $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $timestamp): self
    {
        return $this->setData(self::CREATED_AT, $timestamp);
    }
}
