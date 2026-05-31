<?php
/**
 * Etechflow_AbandonedCart - PopupFrequency admin dropdown source.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Source;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class PopupFrequency implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => PopupRuleInterface::FREQUENCY_ONCE_PER_SESSION,  'label' => __('Once per Session (resets on browser close)')],
            ['value' => PopupRuleInterface::FREQUENCY_ONCE_PER_DAY,      'label' => __('Once per Day')],
            ['value' => PopupRuleInterface::FREQUENCY_ONCE_PER_LIFETIME, 'label' => __('Once per Customer Lifetime')],
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
