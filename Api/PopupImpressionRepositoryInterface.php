<?php
/**
 * Etechflow_AbandonedCart - PopupImpression repository contract.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Api;

use Etechflow\AbandonedCart\Api\Data\PopupImpressionInterface;

interface PopupImpressionRepositoryInterface
{
    /**
     * @param PopupImpressionInterface $impression
     * @return PopupImpressionInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(PopupImpressionInterface $impression): PopupImpressionInterface;

    /**
     * @param int $id
     * @return PopupImpressionInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): PopupImpressionInterface;

    /**
     * @param PopupImpressionInterface $impression
     * @return bool
     */
    public function delete(PopupImpressionInterface $impression): bool;

    /**
     * @param int $id
     * @return bool
     */
    public function deleteById(int $id): bool;

    /**
     * Count how many times a specific session has been shown a specific
     * rule's popup. Used by the frequency-cap check before re-displaying.
     */
    public function countBySessionAndRule(string $sessionId, int $ruleId): int;

    /**
     * Count impressions for a known customer (across all sessions).
     * Used for once_per_lifetime frequency cap.
     */
    public function countByCustomerAndRule(string $customerEmail, int $ruleId): int;

    /**
     * @param int $ruleId
     * @return PopupImpressionInterface[]
     */
    public function getByPopupRuleId(int $ruleId): array;

    /**
     * @return PopupImpressionInterface[]
     */
    public function getAll(): array;
}
