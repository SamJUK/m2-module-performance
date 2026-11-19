<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\ConfigurableProduct\Model\AttributeOptionProvider;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ScopeResolverInterface;

// Loading one configurable builds its option matrix three times per attribute: the configurable read handler,
// then getConfigurableAttributes() and getConfigurableOptions() from MSI's AdaptAssignStatusToProductPlugin.
class MemoiseConfigurableOptions
{
    private array $options = [];

    public function __construct(
        private readonly ScopeResolverInterface $scopeResolver,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function aroundGetAttributeOptions(
        AttributeOptionProvider $subject,
        callable $proceed,
        AbstractAttribute $superAttribute,
        $productId
    ) {
        if (!$this->scopeConfig->isSetFlag('samjuk_performance/general/queries')) {
            return $proceed($superAttribute, $productId);
        }
        $key = $superAttribute->getAttributeId() . '|' . $productId . '|' . $this->scopeResolver->getScope()->getId();
        return $this->options[$key] ??= $proceed($superAttribute, $productId);
    }
}
