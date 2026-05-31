<?php
/**
 * Etechflow_AbandonedCart - PopupRule repository contract.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Api;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;

interface PopupRuleRepositoryInterface
{
    /**
     * @param PopupRuleInterface $rule
     * @return PopupRuleInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(PopupRuleInterface $rule): PopupRuleInterface;

    /**
     * @param int $id
     * @return PopupRuleInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): PopupRuleInterface;

    /**
     * @param PopupRuleInterface $rule
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(PopupRuleInterface $rule): bool;

    /**
     * @param int $id
     * @return bool
     */
    public function deleteById(int $id): bool;

    /**
     * @param int|null $storeId
     * @return PopupRuleInterface[]
     */
    public function getAll(?int $storeId = null): array;

    /**
     * Return every ACTIVE rule, ordered by priority ASC.
     * Hot path for ConfigProvider (Phase 28) which delivers rules to the
     * frontend on every storefront page render.
     *
     * @param int|null $storeId
     * @param string|null $pageScope  Filter to a specific page scope (cart/checkout/all)
     * @return PopupRuleInterface[]
     */
    public function getActiveRules(?int $storeId = null, ?string $pageScope = null): array;
}
