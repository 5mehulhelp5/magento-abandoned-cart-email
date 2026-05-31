<?php
/**
 * Etechflow_AbandonedCart - PopupRule repository implementation.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Etechflow\AbandonedCart\Api\PopupRuleRepositoryInterface;
use Etechflow\AbandonedCart\Model\PopupRuleFactory;
use Etechflow\AbandonedCart\Model\ResourceModel\PopupRule as PopupRuleResource;
use Etechflow\AbandonedCart\Model\ResourceModel\PopupRule\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class PopupRuleRepository implements PopupRuleRepositoryInterface
{
    public function __construct(
        private readonly PopupRuleResource $resource,
        private readonly PopupRuleFactory $modelFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function save(PopupRuleInterface $rule): PopupRuleInterface
    {
        try {
            /** @var \Magento\Framework\Model\AbstractModel $rule */
            $this->resource->save($rule);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to save popup rule',
                ['rule_id' => $rule->getRuleId(), 'name' => $rule->getName(), 'exception' => $e->getMessage()]
            );
            throw new CouldNotSaveException(__('Could not save popup rule: %1', $e->getMessage()), $e);
        }
        return $rule;
    }

    public function getById(int $id): PopupRuleInterface
    {
        $rule = $this->modelFactory->create();
        $this->resource->load($rule, $id);
        if (!$rule->getId()) {
            throw new NoSuchEntityException(__('No popup rule with rule_id = %1', $id));
        }
        return $rule;
    }

    public function delete(PopupRuleInterface $rule): bool
    {
        try {
            /** @var \Magento\Framework\Model\AbstractModel $rule */
            $this->resource->delete($rule);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to delete popup rule',
                ['rule_id' => $rule->getRuleId(), 'exception' => $e->getMessage()]
            );
            throw new CouldNotDeleteException(__('Could not delete popup rule: %1', $e->getMessage()), $e);
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
        $collection->setOrder(PopupRuleInterface::PRIORITY, 'ASC');
        $rules = array_values($collection->getItems());
        if ($storeId !== null) {
            $rules = $this->filterByStoreId($rules, $storeId);
        }
        return $rules;
    }

    public function getActiveRules(?int $storeId = null, ?string $pageScope = null): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PopupRuleInterface::IS_ACTIVE, 1);
        $collection->setOrder(PopupRuleInterface::PRIORITY, 'ASC');

        if ($pageScope !== null) {
            // Match either exact scope OR "all" (all-pages rule)
            $collection->addFieldToFilter(
                PopupRuleInterface::PAGE_SCOPE,
                ['in' => [$pageScope, PopupRuleInterface::SCOPE_ALL]]
            );
        }

        $rules = array_values($collection->getItems());

        if ($storeId !== null) {
            $rules = $this->filterByStoreId($rules, $storeId);
        }

        return $rules;
    }

    /**
     * Filter rules by store_ids CSV. "0" means all stores.
     *
     * @param PopupRuleInterface[] $rules
     * @return PopupRuleInterface[]
     */
    private function filterByStoreId(array $rules, int $storeId): array
    {
        return array_values(array_filter($rules, static function (PopupRuleInterface $rule) use ($storeId): bool {
            $storeIds = array_map('trim', explode(',', $rule->getStoreIds()));
            return in_array('0', $storeIds, true) || in_array((string) $storeId, $storeIds, true);
        }));
    }
}
