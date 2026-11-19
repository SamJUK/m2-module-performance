<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Review\Block\Form;
use WeakMap;

// Form::getRatings() builds and loads a new collection on every call; Hyvä's form.phtml calls it five times.
class MemoiseReviewFormRatings
{
    private WeakMap $ratings;

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
        $this->ratings = new WeakMap();
    }

    public function aroundGetRatings(Form $subject, callable $proceed)
    {
        if (!$this->scopeConfig->isSetFlag('samjuk_performance/general/queries')) {
            return $proceed();
        }
        return $this->ratings[$subject] ??= $proceed();
    }
}
