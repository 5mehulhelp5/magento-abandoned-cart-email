<?php
/**
 * Etechflow_AbandonedCart - Base class for Email Rule edit-form toolbar buttons.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Block\Adminhtml\Rule\Edit;

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
