<?php
/**
 * Etechflow_AbandonedCart - PopupTriggerType admin dropdown source.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Source;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class PopupTriggerType implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => PopupRuleInterface::TRIGGER_EXIT_INTENT,   'label' => __('Exit Intent (mouse leaves viewport)')],
            ['value' => PopupRuleInterface::TRIGGER_TIME_ON_PAGE,  'label' => __('Time on Page (seconds)')],
            ['value' => PopupRuleInterface::TRIGGER_SCROLL_DEPTH,  'label' => __('Scroll Depth (% of page)')],
            ['value' => PopupRuleInterface::TRIGGER_CART_SUBTOTAL, 'label' => __('Cart Subtotal Threshold (when subtotal crosses min/max)')],
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
