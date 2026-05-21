<?php
/**
 * Etechflow_AbandonedCart - Per-email coupon generator.
 *
 * Creates a single-use coupon code attached to a Magento sales rule. The
 * merchant configures which sales rule to use per email rule (Phase 17
 * admin form will expose this). At send time, EmailSender calls
 * generateForCart() to mint a unique code, which gets baked into the
 * "with-coupon" email template.
 *
 * Implementation notes:
 *   - We use Magento\SalesRule\Model\Coupon directly (lightweight). The
 *     fancier `\Magento\SalesRule\Api\CouponManagementInterface` exists
 *     but requires a CouponSpecification and is overkill for our 1-code
 *     use case.
 *   - Codes look like `ETF-XXXXXXXX` (8 random hex chars, uppercased) —
 *     short enough to read aloud, long enough to be unguessable.
 *   - Single-use is enforced both via the coupon's usage_limit=1 AND by
 *     the merchant's sales-rule configuration (uses_per_customer=1
 *     recommended).
 *   - Expiry defaults to 30 days from now (matches restore-token expiry
 *     default). Customer has the full restore window to redeem.
 *
 * Silent-fail per §19: if the parent sales rule doesn't exist or coupon
 * save throws, we log + return null. The email still goes out, just
 * without a coupon block (template's `{{depend coupon_code}}` skips that
 * section gracefully).
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\RuleFactory as SalesRuleFactory;
use Psr\Log\LoggerInterface;

class CouponGenerator
{
    private const CODE_PREFIX = 'ETF-';
    private const CODE_RANDOM_BYTES = 4;
    private const COUPON_EXPIRY_DAYS = 30;

    public function __construct(
        private readonly CouponFactory $couponFactory,
        private readonly SalesRuleFactory $salesRuleFactory,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Generate a fresh single-use coupon attached to the given sales rule.
     * Returns the code string on success, null on any failure (the email
     * still sends, just without a coupon).
     */
    public function generateForCart(int $cartId, int $salesRuleId): ?string
    {
        if ($salesRuleId <= 0) {
            return null;
        }

        try {
            $rule = $this->salesRuleFactory->create()->load($salesRuleId);
            if (!$rule->getId()) {
                $this->logger->warning(
                    'Etechflow_AbandonedCart: sales rule not found for coupon generation',
                    ['sales_rule_id' => $salesRuleId, 'cart_id' => $cartId]
                );
                return null;
            }

            $code = $this->generateUniqueCode();

            $coupon = $this->couponFactory->create();
            $coupon->setRuleId($salesRuleId);
            $coupon->setCode($code);
            $coupon->setIsPrimary(0);
            $coupon->setUsageLimit(1);
            $coupon->setUsagePerCustomer(1);
            $coupon->setTimesUsed(0);
            $coupon->setExpirationDate(
                $this->dateTime->gmtDate(null, time() + (self::COUPON_EXPIRY_DAYS * 86400))
            );
            $coupon->setType(1);
            $coupon->save();

            return $code;
        } catch (\Throwable $e) {
            $this->logger->error(
                'Etechflow_AbandonedCart: coupon generation failed',
                [
                    'cart_id'        => $cartId,
                    'sales_rule_id'  => $salesRuleId,
                    'exception'      => $e->getMessage(),
                ]
            );
            return null;
        }
    }

    private function generateUniqueCode(): string
    {
        return self::CODE_PREFIX . strtoupper(bin2hex(random_bytes(self::CODE_RANDOM_BYTES)));
    }
}
