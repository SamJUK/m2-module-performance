<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\Config\ScopeConfigInterface;

// A PDP asks canBeShowInCategory three times with the same arguments, and re-queries category and website IDs
// that the product's read handlers already put in its extension attributes during load.
class ReuseProductLoadData
{
    /** @var array<string, string|false> */
    private array $shown = [];

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * @param Product|int $product
     * @param int|string $categoryId
     * @return string|false
     */
    public function aroundCanBeShowInCategory(ProductResource $subject, callable $proceed, $product, $categoryId)
    {
        if (!$this->enabled()) {
            return $proceed($product, $categoryId);
        }
        $key = $product instanceof Product ? $product->getEntityId() . '|' . $product->getStoreId() : $product . '|';
        return $this->shown[$key . '|' . (int) $categoryId] ??= $proceed($product, $categoryId);
    }

    /**
     * @param Product|int $product
     * @return array<int|string>
     */
    public function aroundGetCategoryIds(ProductResource $subject, callable $proceed, $product): array
    {
        $links = $this->enabled() && $product instanceof Product
            ? $this->extension($product)->getCategoryLinks()
            : null;
        return $links === null ? $proceed($product) : array_map(static fn ($link) => $link->getCategoryId(), $links);
    }

    /**
     * @param Product|int $product
     * @return array<int|string>
     */
    public function aroundGetWebsiteIds(ProductResource $subject, callable $proceed, $product)
    {
        $ids = $this->enabled() && $product instanceof Product
            ? $this->extension($product)->getWebsiteIds()
            : null;
        return $ids ?? $proceed($product);
    }

    private function extension(Product $product): ProductExtensionInterface
    {
        /** @var ProductExtensionInterface */
        return $product->getExtensionAttributes();
    }

    private function enabled(): bool
    {
        return $this->scopeConfig->isSetFlag('samjuk_performance/general/queries');
    }
}
