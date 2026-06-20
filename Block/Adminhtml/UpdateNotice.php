<?php
declare(strict_types=1);
namespace Etechflow\AbandonedCart\Block\Adminhtml;

use Etechflow\AbandonedCart\Model\UpdateChecker;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Renders the "update available" banner at the top of the module's admin pages.
 */
class UpdateNotice extends Template
{
    public function __construct(
        Context $context,
        private readonly UpdateChecker $updateChecker,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array{installed:string,latest:string,notes:string,package:string}|null */
    public function getUpdateInfo(): ?array
    {
        return $this->updateChecker->getAvailableUpdate();
    }

    public function getUpdateCommand(): string
    {
        return $this->updateChecker->getUpdateCommand();
    }
}
