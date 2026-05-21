<?php
/**
 * Etechflow_AbandonedCart - EmailLog repository contract.
 *
 * Service contract for per-email delivery records. Concrete implementation
 * in Model/EmailLogRepository.php (Phase 7).
 *
 * Log volume grows over time — `cleanup/log_retention_days` (default 180)
 * keeps it bounded. The per-cart history fetch + open/click counter updates
 * are the only frequent operations; both are O(log n) thanks to the
 * `cart_id + sequence_number` composite index in db_schema.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Api;

use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;

interface EmailLogRepositoryInterface
{
    /**
     * Persist an email-log record.
     *
     * @param EmailLogInterface $log
     * @return EmailLogInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(EmailLogInterface $log): EmailLogInterface;

    /**
     * Load an email-log record by its log_id.
     *
     * @param int $id
     * @return EmailLogInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): EmailLogInterface;

    /**
     * Delete an email-log record.
     *
     * @param EmailLogInterface $log
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(EmailLogInterface $log): bool;

    /**
     * Delete an email-log record by its log_id.
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $id): bool;

    /**
     * Return every email-log record for a specific abandoned-cart, ordered by
     * sequence_number ASC. Powers the per-cart email history shown in the
     * admin cart-view page (Phase 16).
     *
     * @param int $cartId
     * @return EmailLogInterface[]
     */
    public function getByCartId(int $cartId): array;

    /**
     * Return every email-log record (optionally filtered by store via cart join).
     * Used by cleanup cron and the reports dashboard.
     *
     * @param int|null $storeId
     * @return EmailLogInterface[]
     */
    public function getAll(?int $storeId = null): array;
}
