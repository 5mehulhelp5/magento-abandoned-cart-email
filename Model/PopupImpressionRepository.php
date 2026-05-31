<?php
/**
 * Etechflow_AbandonedCart - PopupImpression repository implementation.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\PopupImpressionInterface;
use Etechflow\AbandonedCart\Api\PopupImpressionRepositoryInterface;
use Etechflow\AbandonedCart\Model\PopupImpressionFactory;
use Etechflow\AbandonedCart\Model\ResourceModel\PopupImpression as PopupImpressionResource;
use Etechflow\AbandonedCart\Model\ResourceModel\PopupImpression\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class PopupImpressionRepository implements PopupImpressionRepositoryInterface
{
    public function __construct(
        private readonly PopupImpressionResource $resource,
        private readonly PopupImpressionFactory $modelFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function save(PopupImpressionInterface $impression): PopupImpressionInterface
    {
        try {
            /** @var \Magento\Framework\Model\AbstractModel $impression */
            $this->resource->save($impression);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to save popup impression',
                [
                    'impression_id'  => $impression->getImpressionId(),
                    'popup_rule_id'  => $impression->getPopupRuleId(),
                    'session_id'     => $impression->getSessionId(),
                    'exception'      => $e->getMessage(),
                ]
            );
            throw new CouldNotSaveException(__('Could not save popup impression: %1', $e->getMessage()), $e);
        }
        return $impression;
    }

    public function getById(int $id): PopupImpressionInterface
    {
        $impression = $this->modelFactory->create();
        $this->resource->load($impression, $id);
        if (!$impression->getId()) {
            throw new NoSuchEntityException(__('No popup impression with impression_id = %1', $id));
        }
        return $impression;
    }

    public function delete(PopupImpressionInterface $impression): bool
    {
        try {
            /** @var \Magento\Framework\Model\AbstractModel $impression */
            $this->resource->delete($impression);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to delete popup impression',
                ['impression_id' => $impression->getImpressionId(), 'exception' => $e->getMessage()]
            );
            throw new CouldNotDeleteException(__('Could not delete popup impression: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }

    public function countBySessionAndRule(string $sessionId, int $ruleId): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PopupImpressionInterface::POPUP_RULE_ID, $ruleId);
        $collection->addFieldToFilter(PopupImpressionInterface::SESSION_ID, $sessionId);
        return (int) $collection->getSize();
    }

    public function countByCustomerAndRule(string $customerEmail, int $ruleId): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PopupImpressionInterface::POPUP_RULE_ID, $ruleId);
        $collection->addFieldToFilter(PopupImpressionInterface::CUSTOMER_EMAIL, $customerEmail);
        return (int) $collection->getSize();
    }

    public function getByPopupRuleId(int $ruleId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PopupImpressionInterface::POPUP_RULE_ID, $ruleId);
        $collection->setOrder(PopupImpressionInterface::SHOWN_AT, 'DESC');
        return array_values($collection->getItems());
    }

    public function getAll(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder(PopupImpressionInterface::SHOWN_AT, 'DESC');
        return array_values($collection->getItems());
    }
}
