<?php
/**
 * Etechflow_AbandonedCart - "Save and Continue Edit" for the Email Rule form.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block\Adminhtml\Rule\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveAndContinueButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        return [
            'label'          => __('Save and Continue Edit'),
            'class'          => 'save',
            'data_attribute' => [
                'mage-init' => [
                    'button' => ['event' => 'saveAndContinueEdit'],
                ],
            ],
            'sort_order'     => 80,
        ];
    }
}
