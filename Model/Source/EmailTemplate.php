<?php
/**
 * Etechflow_AbandonedCart - Module-scoped email template dropdown source.
 *
 * Replaces Magento's standard Source\Email\Template at admin config so the
 * dropdown shows ONLY our 3 templates (default, hyva, with-coupon), not the
 * 69 templates registered globally. Two reasons:
 *
 *   1. UX — merchants don't need to scroll past sales order / invoice /
 *      shipment / credit-memo templates to pick a cart-recovery one.
 *   2. Robustness — Magento's standard source calls getTemplateLabel() on
 *      every registered template; if ANY one is broken (e.g., theme-override
 *      reference to a missing file), the entire dropdown throws. Limiting
 *      to OUR 3 templates avoids that landmine.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class EmailTemplate implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'etechflow_abandoned_cart_default_template',
                'label' => __('Default Reminder (Luma)'),
            ],
            [
                'value' => 'etechflow_abandoned_cart_hyva_template',
                'label' => __('Hyvä-Styled Reminder'),
            ],
            [
                'value' => 'etechflow_abandoned_cart_with_coupon_template',
                'label' => __('Default Reminder + Discount Coupon'),
            ],
        ];
    }
}
