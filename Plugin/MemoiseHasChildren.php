<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Catalog\Model\Category;
use Magento\Framework\App\Config\ScopeConfigInterface;

// Catalog\Controller\Category\View asks the current category hasChildren() twice, one query each.
class MemoiseHasChildren
{
    /** @var \WeakMap<Category, bool> */
    private \WeakMap $hasChildren;

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
        $this->hasChildren = new \WeakMap();
    }

    /** @return bool */
    public function aroundHasChildren(Category $subject, callable $proceed)
    {
        return $this->enabled() ? $this->hasChildren[$subject] ??= $proceed() : $proceed();
    }

    private function enabled(): bool
    {
        return $this->scopeConfig->isSetFlag('samjuk_performance/general/queries');
    }
}
