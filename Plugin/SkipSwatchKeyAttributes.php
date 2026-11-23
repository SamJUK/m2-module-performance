<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Swatches\Block\Product\Renderer\Listing\Configurable as ListingRenderer;
use Magento\Swatches\Helper\Data as SwatchHelper;

// The listing swatch block's cache key loads each product's configurable attributes (4 queries) only to intersect
// their codes with the URL query. When no query key is a product attribute at all, that intersection is empty, so
// the attributes aren't loaded and the key comes out the same.
class SkipSwatchKeyAttributes
{
    private bool $skip = false;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RequestInterface $request,
        private readonly EavConfig $eavConfig
    ) {
    }

    /** @return string */
    public function aroundGetCacheKey(ListingRenderer $subject, callable $proceed)
    {
        /** @var HttpRequest $request */
        $request = $this->request;
        if (!$this->scopeConfig->isSetFlag('samjuk_performance/general/queries')
            || array_intersect_key(
                $request->getQuery()->toArray(),
                $this->eavConfig->getEntityAttributes(Product::ENTITY)
            )
        ) {
            return $proceed();
        }
        $this->skip = true;
        try {
            return $proceed();
        } finally {
            $this->skip = false;
        }
    }

    /**
     * @param Product $product
     * @return array<mixed>
     */
    public function aroundGetAttributesFromConfigurable(SwatchHelper $subject, callable $proceed, $product)
    {
        return $this->skip ? [] : $proceed($product);
    }
}
