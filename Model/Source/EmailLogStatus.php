<?php
/**
 * Etechflow_AbandonedCart - EmailLogStatus admin dropdown source.
 *
 * Used by the per-cart email history (admin cart-view, Phase 16) + by the
 * reports dashboard (Phase 18) to render human labels for the integer
 * status stored in `etechflow_abandoned_cart_email_log.status`. Constants
 * live on [[Etechflow\AbandonedCart\Api\Data\EmailLogInterface]].
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Source;

use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class EmailLogStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => EmailLogInterface::STATUS_QUEUED,    'label' => __('Queued')],
            ['value' => EmailLogInterface::STATUS_SENT,      'label' => __('Sent')],
            ['value' => EmailLogInterface::STATUS_FAILED,    'label' => __('Failed')],
            ['value' => EmailLogInterface::STATUS_OPENED,    'label' => __('Opened')],
            ['value' => EmailLogInterface::STATUS_CLICKED,   'label' => __('Clicked')],
            ['value' => EmailLogInterface::STATUS_CONVERTED, 'label' => __('Converted to Order')],
        ];
    }

    public function getOptionText(int $status): Phrase
    {
        foreach ($this->toOptionArray() as $option) {
            if ($option['value'] === $status) {
                return $option['label'];
            }
        }
        return __('');
    }
}
