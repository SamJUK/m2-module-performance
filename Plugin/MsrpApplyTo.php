<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Msrp\Model\Msrp;

// Core loads the msrp attribute straight from the database (4 queries) instead of the cached EAV config.
class MsrpApplyTo
{
    public function __construct(
        private readonly EavConfig $eavConfig,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @param Product $product
     * @return bool
     */
    public function aroundCanApplyToProduct(Msrp $subject, callable $proceed, $product)
    {
        if (!$this->scopeConfig->isSetFlag('samjuk_performance/general/queries')) {
            return $proceed($product);
        }
        return in_array($product->getTypeId(), $this->eavConfig->getAttribute(Product::ENTITY, 'msrp')->getApplyTo());
    }
}
