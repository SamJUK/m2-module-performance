<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Catalog\Model\Layer\Filter\DataProvider\Category;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;

// On a category page the layered category filter gets the page's own id and loads that category again (4 queries).
// A null id makes the provider fall back to the layer's current category, which is the same record.
class ReuseCurrentCategoryInLayer
{
    public function __construct(
        private readonly Registry $registry,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @param int|string|null $categoryId
     * @return array{int|string|null}
     */
    public function beforeSetCategoryId(Category $subject, $categoryId): array
    {
        $current = $this->registry->registry('current_category');
        $same = $current && (string) $current->getId() === (string) $categoryId;
        return [$same && $this->scopeConfig->isSetFlag('samjuk_performance/general/queries') ? null : $categoryId];
    }
}
