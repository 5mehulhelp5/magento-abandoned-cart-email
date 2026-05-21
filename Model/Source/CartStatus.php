<?php
/**
 * Etechflow_AbandonedCart - CartStatus admin dropdown source.
 *
 * Used by the admin Carts grid (Phase 16) for status column filtering + by
 * any UI Component that needs to render a human label for the integer
 * status stored in `etechflow_abandoned_cart.status`. Constants live on
 * [[Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface]] (single source
 * of truth) so labels here cannot drift from enum integers stored in DB.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Source;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class CartStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => AbandonedCartInterface::STATUS_PENDING,      'label' => __('Pending')],
            ['value' => AbandonedCartInterface::STATUS_PROCESSING,   'label' => __('Processing')],
            ['value' => AbandonedCartInterface::STATUS_RECOVERED,    'label' => __('Recovered')],
            ['value' => AbandonedCartInterface::STATUS_EXPIRED,      'label' => __('Expired')],
            ['value' => AbandonedCartInterface::STATUS_UNSUBSCRIBED, 'label' => __('Unsubscribed')],
        ];
    }

    /**
     * Lookup the human label for a single status integer. Returns empty
     * Phrase for unknown values rather than throwing — admin grids tolerate
     * stale data (e.g., a row written by an older module version using an
     * enum value we since removed) and a blank cell is friendlier than a
     * 500 error.
     */
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
