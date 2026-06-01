<?php
/**
 * Etechflow_AbandonedCart - Base class for Popup Rule edit-form toolbar buttons.
 *
 * Common helpers shared by Save/Back/Delete/SaveAndContinue buttons —
 * mainly URL building + rule-id resolution from the current request.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block\Adminhtml\PopupRule\Edit;

use Magento\Backend\Block\Widget\Context;

class GenericButton
{
    public function __construct(
        protected readonly Context $context,
    ) {
    }

    public function getRuleId(): ?int
    {
        $id = (int) $this->context->getRequest()->getParam('rule_id');
        return $id > 0 ? $id : null;
    }

    public function getUrl(string $route, array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
