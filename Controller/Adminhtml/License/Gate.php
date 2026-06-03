<?php
/**
 * Etechflow_AbandonedCart - License gate page.
 *
 * Unlicensed admins are redirected here from every gated landing controller
 * (Cart Index, Rule Index, PopupRule Index, Report Index). Shows the 3 plan
 * cards (weekly/monthly/yearly) with Stripe checkout buttons + a "Paste
 * License Key" link for customers who already have a key from manual sales.
 *
 * If the license validator returns true (already licensed), redirects back
 * to the main admin dashboard.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\License;

use Etechflow\AbandonedCart\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Gate extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::config';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly LicenseValidator $licenseValidator,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if ($this->licenseValidator->isValid()) {
            return $this->resultFactory
                ->create(ResultFactory::TYPE_REDIRECT)
                ->setPath('etechflow_abandonedcart/cart/index');
        }

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Etechflow_AbandonedCart::main');
        $page->getConfig()->getTitle()->prepend(__('Activate Your License'));
        return $page;
    }
}
