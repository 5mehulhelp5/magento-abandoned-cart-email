<?php
/**
 * Etechflow_AbandonedCart - EmailLog repository implementation.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Api\EmailLogRepositoryInterface;
use Etechflow\AbandonedCart\Model\EmailLogFactory;
use Etechflow\AbandonedCart\Model\ResourceModel\EmailLog as EmailLogResource;
use Etechflow\AbandonedCart\Model\ResourceModel\EmailLog\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class EmailLogRepository implements EmailLogRepositoryInterface
{
    public function __construct(
        private readonly EmailLogResource $resource,
        private readonly EmailLogFactory $modelFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function save(EmailLogInterface $log): EmailLogInterface
    {
        try {
            /** @var \Magento\Framework\Model\AbstractModel $log */
            $this->resource->save($log);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to save email log',
                [
                    'cart_id'   => $log->getCartId(),
                    'rule_id'   => $log->getRuleId(),
                    'exception' => $e->getMessage(),
                ]
            );
            throw new CouldNotSaveException(
                __('Could not save email log: %1', $e->getMessage()),
                $e
            );
        }
        return $log;
    }

    public function getById(int $id): EmailLogInterface
    {
        $log = $this->modelFactory->create();
        $this->resource->load($log, $id);
        if (!$log->getId()) {
            throw new NoSuchEntityException(
                __('No email-log record with log_id = %1', $id)
            );
        }
        return $log;
    }

    public function delete(EmailLogInterface $log): bool
    {
        try {
            /** @var \Magento\Framework\Model\AbstractModel $log */
            $this->resource->delete($log);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to delete email log',
                [
                    'log_id'    => $log->getLogId(),
                    'exception' => $e->getMessage(),
                ]
            );
            throw new CouldNotDeleteException(
                __('Could not delete email log: %1', $e->getMessage()),
                $e
            );
        }
        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }

    public function getByCartId(int $cartId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(EmailLogInterface::CART_ID, $cartId);
        $collection->setOrder(EmailLogInterface::SEQUENCE_NUMBER, 'ASC');
        return array_values($collection->getItems());
    }

    public function getAll(?int $storeId = null): array
    {
        $collection = $this->collectionFactory->create();
        return array_values($collection->getItems());
    }
}
