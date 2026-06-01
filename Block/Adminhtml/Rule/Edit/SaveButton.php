<?php
/**
 * Etechflow_AbandonedCart - "Save" primary action for the Email Rule form.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block\Adminhtml\Rule\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        return [
            'label'          => __('Save'),
            'class'          => 'save primary',
            'data_attribute' => [
                'mage-init'  => ['button' => ['event' => 'save']],
                'form-role'  => 'save',
            ],
            'sort_order'     => 90,
        ];
    }
}
