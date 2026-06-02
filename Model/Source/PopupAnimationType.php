<?php
/**
 * Etechflow_AbandonedCart - PopupAnimationType admin dropdown source.
 *
 * 4 entrance animations available per popup rule. Frontend JS adds
 * a matching CSS class (etechflow-popup--<animation>) on render.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Source;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class PopupAnimationType implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => PopupRuleInterface::ANIMATION_FADE_IN,  'label' => __('Fade In')],
            ['value' => PopupRuleInterface::ANIMATION_SLIDE_UP, 'label' => __('Slide Up')],
            ['value' => PopupRuleInterface::ANIMATION_ZOOM_IN,  'label' => __('Zoom In (default)')],
            ['value' => PopupRuleInterface::ANIMATION_BOUNCE,   'label' => __('Bounce')],
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
