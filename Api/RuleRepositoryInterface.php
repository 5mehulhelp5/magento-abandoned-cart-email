<?php
/**
 * Etechflow_AbandonedCart - Rule repository contract.
 *
 * Service contract for the admin-managed email rules. Concrete implementation
 * in Model/RuleRepository.php (Phase 7).
 *
 * Rule counts stay small in practice — Amasty docs cap at 9 rules per cart
 * and most merchants ship 3-5 total. getAll() with optional store-id filter
 * is cheap; SearchCriteria would be over-engineering per §7.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Api;

use Etechflow\AbandonedCart\Api\Data\RuleInterface;

interface RuleRepositoryInterface
{
    /**
     * Persist a rule.
     *
     * @param RuleInterface $rule
     * @return RuleInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(RuleInterface $rule): RuleInterface;

    /**
     * Load a rule by its rule_id.
     *
     * @param int $id
     * @return RuleInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): RuleInterface;

    /**
     * Delete a rule.
     *
     * @param RuleInterface $rule
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(RuleInterface $rule): bool;

    /**
     * Delete a rule by its rule_id.
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $id): bool;

    /**
     * Return every rule, ordered by priority ASC (lower fires first).
     *
     * @param int|null $storeId
     * @return RuleInterface[]
     */
    public function getAll(?int $storeId = null): array;

    /**
     * Return every ACTIVE rule, ordered by priority ASC.
     *
     * Used by the cron tick (Phase 10) — narrower hot-path query than
     * getAll() so admin-disabled rules never enter the matching loop.
     *
     * @param int|null $storeId
     * @return RuleInterface[]
     */
    public function getActiveRules(?int $storeId = null): array;
}
