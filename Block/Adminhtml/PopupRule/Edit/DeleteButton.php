<?php
/**
 * Etechflow_AbandonedCart - "Delete" action for the Popup Rule form.
 *
 * Only renders when editing an existing rule (rule_id present).
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block\Adminhtml\PopupRule\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        $id = $this->getRuleId();
        if ($id === null) {
            return [];
        }
        return [
            'label'      => __('Delete Rule'),
            'class'      => 'delete',
            'on_click'   => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Are you sure you want to delete this popup rule?'),
                $this->getUrl('*/*/delete', ['rule_id' => $id])
            ),
            'sort_order' => 20,
        ];
    }
}
