<?php
/**
 * Etechflow_AbandonedCart - Admin Reports dashboard controller.
 *
 * Parses `from`/`to` URL params (defaulting to last 30 days), hands them
 * to the block via Registry, and renders the dashboard layout.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Etechflow_AbandonedCart::reports';

    public const REGISTRY_DATE_RANGE = 'etechflow_report_date_range';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly Registry $registry,
        private readonly DateTime $dateTime,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $now = time();
        $defaultFrom = $this->dateTime->gmtDate('Y-m-d', $now - (30 * 86400));
        $defaultTo   = $this->dateTime->gmtDate('Y-m-d', $now);

        $from = (string) $this->getRequest()->getParam('from', $defaultFrom);
        $to   = (string) $this->getRequest()->getParam('to', $defaultTo);

        // Normalize / validate (fall back to defaults if invalid)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = $defaultFrom;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $defaultTo;
        }

        $this->registry->register(
            self::REGISTRY_DATE_RANGE,
            ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59', 'from_date' => $from, 'to_date' => $to],
            true
        );

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Etechflow_AbandonedCart::reports');
        $page->getConfig()->getTitle()->prepend(__('Recovery Reports'));
        $page->addBreadcrumb(__('ETechFlow Abandoned Cart'), __('ETechFlow Abandoned Cart'));
        $page->addBreadcrumb(__('Reports'), __('Reports'));
        return $page;
    }
}
