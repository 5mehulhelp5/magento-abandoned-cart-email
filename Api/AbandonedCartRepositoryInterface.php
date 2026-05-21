<?php
/**
 * Etechflow_AbandonedCart - AbandonedCart repository contract.
 *
 * Service contract per ETechFlow Module Development Standards §7. Concrete
 * implementation in Model/AbandonedCartRepository.php (Phase 7). DI binding
 * lives in etc/di.xml (also Phase 7) — interface → repository class.
 *
 * SearchCriteria roundtrips intentionally skipped per §7 guidance — at
 * realistic abandoned-cart counts (≤ a few thousand pending at any moment,
 * processed by cron in batches of 50) `getAll(?int $storeId)` plus the
 * cron-specific lookups in Phase 9 are sufficient. If REST/SOAP exposure is
 * ever needed, add SearchCriteriaInterface back here in a minor bump.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Api;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;

interface AbandonedCartRepositoryInterface
{
    /**
     * Persist an abandoned-cart record.
     *
     * @param AbandonedCartInterface $cart
     * @return AbandonedCartInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(AbandonedCartInterface $cart): AbandonedCartInterface;

    /**
     * Load an abandoned-cart record by its entity_id.
     *
     * @param int $id
     * @return AbandonedCartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): AbandonedCartInterface;

    /**
     * Load an abandoned-cart record by its restore_token.
     *
     * Used by the 1-click cart-restore controller (Phase 13).
     *
     * @param string $token
     * @return AbandonedCartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByRestoreToken(string $token): AbandonedCartInterface;

    /**
     * Load an abandoned-cart record by its quote_id.
     *
     * Used by the CartSaveObserver to dedupe across quote saves (Phase 9).
     *
     * @param int $quoteId
     * @return AbandonedCartInterface|null
     */
    public function getByQuoteId(int $quoteId): ?AbandonedCartInterface;

    /**
     * Delete an abandoned-cart record.
     *
     * @param AbandonedCartInterface $cart
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(AbandonedCartInterface $cart): bool;

    /**
     * Delete an abandoned-cart record by its entity_id.
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $id): bool;

    /**
     * Return every abandoned-cart record (optionally filtered by store).
     *
     * @param int|null $storeId
     * @return AbandonedCartInterface[]
     */
    public function getAll(?int $storeId = null): array;
}
