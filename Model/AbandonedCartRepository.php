<?php
/**
 * Etechflow_AbandonedCart - AbandonedCart repository implementation.
 *
 * Wraps the resource model + collection factory in the service-contract
 * shape declared by [[Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface]].
 *
 * Why factories instead of direct constructor injection of the model:
 *   AbstractModel-derived classes are stateful (they hold the row data). DI
 *   would inject one shared instance — fine for stateless services but not
 *   for entities. Factories give us a fresh instance per save/load cycle.
 *
 * Why try/catch with structured logger:
 *   Per §19, hot-path callers (observer, cron) must not be surprised by raw
 *   DB exceptions. We wrap into CouldNotSaveException / CouldNotDeleteException
 *   (Magento's standard recovery types) and log the original cause with
 *   context.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Model\AbandonedCartFactory;
use Etechflow\AbandonedCart\Model\ResourceModel\AbandonedCart as AbandonedCartResource;
use Etechflow\AbandonedCart\Model\ResourceModel\AbandonedCart\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class AbandonedCartRepository implements AbandonedCartRepositoryInterface
{
    public function __construct(
        private readonly AbandonedCartResource $resource,
        private readonly AbandonedCartFactory $modelFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function save(AbandonedCartInterface $cart): AbandonedCartInterface
    {
        try {
            /** @var \Magento\Framework\Model\AbstractModel $cart */
            $this->resource->save($cart);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to save abandoned cart',
                [
                    'quote_id'  => $cart->getQuoteId(),
                    'exception' => $e->getMessage(),
                ]
            );
            throw new CouldNotSaveException(
                __('Could not save abandoned cart: %1', $e->getMessage()),
                $e
            );
        }
        return $cart;
    }

    public function getById(int $id): AbandonedCartInterface
    {
        $cart = $this->modelFactory->create();
        $this->resource->load($cart, $id);
        if (!$cart->getId()) {
            throw new NoSuchEntityException(
                __('No abandoned-cart record with entity_id = %1', $id)
            );
        }
        return $cart;
    }

    public function getByRestoreToken(string $token): AbandonedCartInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(AbandonedCartInterface::RESTORE_TOKEN, $token);
        $collection->setPageSize(1);

        $cart = $collection->getFirstItem();
        if (!$cart->getId()) {
            throw new NoSuchEntityException(
                __('No abandoned-cart record with restore_token')
            );
        }
        return $cart;
    }

    public function getByQuoteId(int $quoteId): ?AbandonedCartInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(AbandonedCartInterface::QUOTE_ID, $quoteId);
        $collection->setPageSize(1);

        $cart = $collection->getFirstItem();
        return $cart->getId() ? $cart : null;
    }

    public function delete(AbandonedCartInterface $cart): bool
    {
        try {
            /** @var \Magento\Framework\Model\AbstractModel $cart */
            $this->resource->delete($cart);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to delete abandoned cart',
                [
                    'entity_id' => $cart->getEntityId(),
                    'exception' => $e->getMessage(),
                ]
            );
            throw new CouldNotDeleteException(
                __('Could not delete abandoned cart: %1', $e->getMessage()),
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
        if ($storeId !== null) {
            $collection->addFieldToFilter(AbandonedCartInterface::STORE_ID, $storeId);
        }
        return array_values($collection->getItems());
    }
}
