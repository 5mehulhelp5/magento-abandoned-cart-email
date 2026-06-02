<?php
/**
 * Etechflow_AbandonedCart - PopupTemplateLayout admin dropdown source.
 *
 * 4 visual layouts available per popup rule. Frontend JS picks the
 * matching renderer based on rule.template_layout.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Source;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class PopupTemplateLayout implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => PopupRuleInterface::LAYOUT_MODAL,      'label' => __('Modal (centered overlay)')],
            ['value' => PopupRuleInterface::LAYOUT_SLIDE_IN,   'label' => __('Slide-In (bottom-right corner)')],
            ['value' => PopupRuleInterface::LAYOUT_BOTTOM_BAR, 'label' => __('Bottom Bar (full-width strip)')],
            ['value' => PopupRuleInterface::LAYOUT_TOP_BAR,    'label' => __('Top Bar (full-width strip)')],
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
