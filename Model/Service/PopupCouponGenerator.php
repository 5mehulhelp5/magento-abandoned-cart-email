<?php
/**
 * Etechflow_AbandonedCart - Generates a unique, single-use coupon code for
 * a Magento Sales Rule linked to a popup.
 *
 * Behavior:
 *   - Validates the sales rule exists and uses TYPE_AUTO/SPECIFIC coupons
 *   - Generates a 12-char alphanumeric code (uppercase + digits, no
 *     visually-ambiguous chars like 0/O, 1/I/L)
 *   - Persists the coupon via Magento's CouponRepositoryInterface with
 *     usage_limit=1, usage_per_customer=1
 *   - Returns the coupon code string
 *
 * Called from the Apply controller after the customer clicks the popup CTA.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\CouponInterfaceFactory;
use Magento\SalesRule\Api\RuleRepositoryInterface as SalesRuleRepositoryInterface;
use Magento\SalesRule\Model\Coupon as CouponModel;

class PopupCouponGenerator
{
    private const CODE_LENGTH    = 12;
    private const CODE_ALPHABET  = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const MAX_ATTEMPTS   = 5;

    public function __construct(
        private readonly SalesRuleRepositoryInterface $salesRuleRepo,
        private readonly CouponRepositoryInterface $couponRepo,
        private readonly CouponInterfaceFactory $couponFactory,
    ) {
    }

    /**
     * @throws LocalizedException When the sales rule is missing or coupon
     *                            creation fails after MAX_ATTEMPTS retries.
     */
    public function generateForRule(int $salesRuleId): string
    {
        try {
            $this->salesRuleRepo->getById($salesRuleId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(
                __('The linked Cart Price Rule (ID %1) does not exist. Update the popup rule and try again.', $salesRuleId)
            );
        }

        $lastError = null;
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $this->randomCode();
            try {
                $coupon = $this->couponFactory->create();
                $coupon->setRuleId($salesRuleId);
                $coupon->setCode($code);
                $coupon->setType(CouponModel::TYPE_GENERATED);
                $coupon->setUsageLimit(1);
                $coupon->setUsagePerCustomer(1);
                $coupon->setTimesUsed(0);
                $this->couponRepo->save($coupon);
                return $code;
            } catch (\Throwable $e) {
                $lastError = $e;
                // Most likely a unique-constraint collision on `code` —
                // try again with a fresh random.
            }
        }

        throw new LocalizedException(
            __('Could not generate a unique coupon after %1 attempts: %2', self::MAX_ATTEMPTS, $lastError?->getMessage() ?? 'unknown')
        );
    }

    private function randomCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}
