<?php
/**
 * Etechflow_AbandonedCart - Rule repository implementation.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\RuleInterface;
use Etechflow\AbandonedCart\Api\RuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\RuleFactory;
use Etechflow\AbandonedCart\Model\ResourceModel\Rule as RuleResource;
use Etechflow\AbandonedCart\Model\ResourceModel\Rule\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class RuleRepository implements RuleRepositoryInterface
{
    public function __construct(
        private readonly RuleResource $resource,
        private readonly RuleFactory $modelFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function save(RuleInterface $rule): RuleInterface
    {
        try {
            /** @var \Magento\Framework\Model\AbstractModel $rule */
            $this->resource->save($rule);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to save rule',
                [
                    'rule_id'   => $rule->getRuleId(),
                    'name'      => $rule->getName(),
                    'exception' => $e->getMessage(),
                ]
            );
            throw new CouldNotSaveException(
                __('Could not save rule: %1', $e->getMessage()),
                $e
            );
        }
        return $rule;
    }

    public function getById(int $id): RuleInterface
    {
        $rule = $this->modelFactory->create();
        $this->resource->load($rule, $id);
        if (!$rule->getId()) {
            throw new NoSuchEntityException(
                __('No rule with rule_id = %1', $id)
            );
        }
        return $rule;
    }

    public function delete(RuleInterface $rule): bool
    {
        try {
            /** @var \Magento\Framework\Model\AbstractModel $rule */
            $this->resource->delete($rule);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to delete rule',
                [
                    'rule_id'   => $rule->getRuleId(),
                    'exception' => $e->getMessage(),
                ]
            );
            throw new CouldNotDeleteException(
                __('Could not delete rule: %1', $e->getMessage()),
                $e
            );
        }
        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }

    public function getAll(?int $storeId = null): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder(RuleInterface::PRIORITY, 'ASC');
        return array_values($collection->getItems());
    }

    public function getActiveRules(?int $storeId = null): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(RuleInterface::IS_ACTIVE, 1);
        $collection->setOrder(RuleInterface::PRIORITY, 'ASC');
        return array_values($collection->getItems());
    }
}
