<?php
/**
 * Etechflow_AbandonedCart - PopupDeviceType admin filter source.
 *
 * Used by the admin Impressions grid to filter analytics by device.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Source;

use Etechflow\AbandonedCart\Api\Data\PopupImpressionInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class PopupDeviceType implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => PopupImpressionInterface::DEVICE_DESKTOP, 'label' => __('Desktop')],
            ['value' => PopupImpressionInterface::DEVICE_MOBILE,  'label' => __('Mobile')],
            ['value' => PopupImpressionInterface::DEVICE_TABLET,  'label' => __('Tablet')],
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
