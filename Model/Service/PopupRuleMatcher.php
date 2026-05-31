<?php
/**
 * Etechflow_AbandonedCart - Filters popup rules against the current request
 * context (store, customer group, page scope, cart subtotal, frequency cap).
 *
 * Called from the Get controller before returning rules to frontend JS.
 * The repo's `getActiveRules()` already filters is_active + page_scope +
 * store_ids — this service applies the remaining business filters that
 * depend on session/quote state.
 *
 * Each rule must pass ALL of:
 *   - customer_group_ids contains current group OR contains '0' (all)
 *   - apply_to_guests true OR customer is logged in
 *   - cart subtotal within [min_cart_subtotal, max_cart_subtotal] if set
 *   - frequency cap not exhausted (per session / per day / per lifetime)
 *   - max_impressions_per_customer not yet hit
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Service;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Etechflow\AbandonedCart\Api\PopupImpressionRepositoryInterface;

class PopupRuleMatcher
{
    public function __construct(
        private readonly PopupImpressionRepositoryInterface $impressionRepo,
    ) {
    }

    /**
     * @param PopupRuleInterface[] $rules
     * @return PopupRuleInterface[]
     */
    public function filter(
        array $rules,
        int $customerGroupId,
        bool $isGuest,
        ?string $customerEmail,
        string $sessionId,
        ?float $cartSubtotal
    ): array {
        $matched = [];
        foreach ($rules as $rule) {
            if (!$this->matchesGuestFlag($rule, $isGuest)) {
                continue;
            }
            if (!$this->matchesGroupId($rule, $customerGroupId)) {
                continue;
            }
            if (!$this->matchesSubtotal($rule, $cartSubtotal)) {
                continue;
            }
            if ($this->isFrequencyCapped($rule, $sessionId, $customerEmail)) {
                continue;
            }
            if ($this->isImpressionCapped($rule, $sessionId, $customerEmail)) {
                continue;
            }
            $matched[] = $rule;
        }
        return $matched;
    }

    private function matchesGuestFlag(PopupRuleInterface $rule, bool $isGuest): bool
    {
        if ($isGuest && !$rule->isApplyToGuests()) {
            return false;
        }
        return true;
    }

    private function matchesGroupId(PopupRuleInterface $rule, int $customerGroupId): bool
    {
        $groupIds = array_map('trim', explode(',', $rule->getCustomerGroupIds()));
        if (in_array('0', $groupIds, true)) {
            return true;
        }
        return in_array((string) $customerGroupId, $groupIds, true);
    }

    private function matchesSubtotal(PopupRuleInterface $rule, ?float $cartSubtotal): bool
    {
        $min = $rule->getMinCartSubtotal();
        $max = $rule->getMaxCartSubtotal();

        if ($min !== null && ($cartSubtotal === null || $cartSubtotal < $min)) {
            return false;
        }
        if ($max !== null && $cartSubtotal !== null && $cartSubtotal > $max) {
            return false;
        }
        return true;
    }

    /**
     * Returns true if the visitor has already exhausted this rule's frequency
     * window. For "once_per_session" we check session impressions; for
     * "once_per_lifetime" we check by customer_email (guests can't be
     * lifetime-capped — they get session-cap fallback).
     */
    private function isFrequencyCapped(
        PopupRuleInterface $rule,
        string $sessionId,
        ?string $customerEmail
    ): bool {
        $ruleId = (int) $rule->getRuleId();

        switch ($rule->getFrequency()) {
            case PopupRuleInterface::FREQUENCY_ONCE_PER_LIFETIME:
                if ($customerEmail !== null && $customerEmail !== '') {
                    return $this->impressionRepo->countByCustomerAndRule($customerEmail, $ruleId) > 0;
                }
                return $this->impressionRepo->countBySessionAndRule($sessionId, $ruleId) > 0;

            case PopupRuleInterface::FREQUENCY_ONCE_PER_DAY:
                // Cheap proxy: session-cap. A true daily-cap query would need
                // shown_at ≥ 24h-ago — kept simple for v1.1.0; revisit if
                // marketing wants stricter daily semantics.
                return $this->impressionRepo->countBySessionAndRule($sessionId, $ruleId) > 0;

            case PopupRuleInterface::FREQUENCY_ONCE_PER_SESSION:
            default:
                return $this->impressionRepo->countBySessionAndRule($sessionId, $ruleId) > 0;
        }
    }

    private function isImpressionCapped(
        PopupRuleInterface $rule,
        string $sessionId,
        ?string $customerEmail
    ): bool {
        $cap = $rule->getMaxImpressionsPerCustomer();
        if ($cap <= 0) {
            return false;
        }
        $ruleId = (int) $rule->getRuleId();

        if ($customerEmail !== null && $customerEmail !== '') {
            return $this->impressionRepo->countByCustomerAndRule($customerEmail, $ruleId) >= $cap;
        }
        return $this->impressionRepo->countBySessionAndRule($sessionId, $ruleId) >= $cap;
    }
}
