<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\SalesRule\Model\Rule;

// Utility::canProcessRule() calls afterLoad() on every rule it checks, and that queries salesrule_coupon for the
// primary coupon. Rules without coupons never have one (Rule::afterSave() deletes it), so automatic promotions
// cost one query per rule per collector on every totals collect.
class SkipNoCouponLookup
{
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function aroundLoadCouponCode(Rule $subject, callable $proceed): void
    {
        if ($subject->getCouponType() == Rule::COUPON_TYPE_NO_COUPON
            && $this->scopeConfig->isSetFlag('samjuk_performance/general/queries')
        ) {
            return;
        }
        $proceed();
    }
}
