<?php
/**
 * Etechflow_AbandonedCart - PopupPageScope admin dropdown source.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Source;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class PopupPageScope implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => PopupRuleInterface::SCOPE_ALL,      'label' => __('All Storefront Pages')],
            ['value' => PopupRuleInterface::SCOPE_CART,     'label' => __('Cart Page Only')],
            ['value' => PopupRuleInterface::SCOPE_CHECKOUT, 'label' => __('Checkout Page Only')],
            ['value' => PopupRuleInterface::SCOPE_CATEGORY, 'label' => __('Category Pages Only')],
            ['value' => PopupRuleInterface::SCOPE_PRODUCT,  'label' => __('Product Pages Only')],
        ];
    }

    public function getOptionText(string $value): Phrase
    {
        foreach ($this->toOptionArray() as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }
        return __('');
    }
}
