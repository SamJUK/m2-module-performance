<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\Config\ScopeConfigInterface;

// A PDP asks canBeShowInCategory three times with the same arguments, and re-queries category and website IDs
// that the product's read handlers already put in its extension attributes during load.
class ReuseProductLoadData
{
    private array $shown = [];

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function aroundCanBeShowInCategory(ProductResource $subject, callable $proceed, $product, $categoryId)
    {
        if (!$this->enabled()) {
            return $proceed($product, $categoryId);
        }
        $key = $product instanceof Product ? $product->getEntityId() . '|' . $product->getStoreId() : $product . '|';
        return $this->shown[$key . '|' . (int) $categoryId] ??= $proceed($product, $categoryId);
    }

    public function aroundGetCategoryIds(ProductResource $subject, callable $proceed, $product): array
    {
        $links = $this->enabled() && $product instanceof Product
            ? $product->getExtensionAttributes()->getCategoryLinks()
            : null;
        return $links === null ? $proceed($product) : array_map(static fn ($link) => $link->getCategoryId(), $links);
    }

    public function aroundGetWebsiteIds(ProductResource $subject, callable $proceed, $product)
    {
        $ids = $this->enabled() && $product instanceof Product
            ? $product->getExtensionAttributes()->getWebsiteIds()
            : null;
        return $ids ?? $proceed($product);
    }

    private function enabled(): bool
    {
        return $this->scopeConfig->isSetFlag('samjuk_performance/general/queries');
    }
}
