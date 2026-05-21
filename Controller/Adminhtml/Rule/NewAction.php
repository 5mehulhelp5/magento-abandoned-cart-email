<?php
/**
 * Etechflow_AbandonedCart - Admin "Add New Rule" page controller.
 *
 * Action class name is "NewAction" (not "New") because `new` is a PHP
 * reserved word. Magento maps the URL segment `new` to this class.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::rules';

    public function __construct(
        Context $context,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        return $forward->forward('edit');
    }
}
