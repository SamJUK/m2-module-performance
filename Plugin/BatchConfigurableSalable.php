<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\ConfigurableProduct\Model\Product\Type\Collection\SalableProcessor;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;

// Listings call isSaleable() per configurable, and each runs its own salable-children COUNT. The children collection
// takes several parents, so this counts every configurable a loaded collection holds in one grouped query.
class BatchConfigurableSalable
{
    /** @var array<int, array<int, Product>> store => entity id => product */
    private array $pending = [];
    /** @var array<int, array<int, int>> store => entity id => salable children */
    private array $counts = [];
    private \WeakMap $seen;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CollectionFactory $collectionFactory,
        private readonly SalableProcessor $salableProcessor,
        private readonly MetadataPool $metadataPool
    ) {
        $this->seen = new \WeakMap();
    }

    public function afterLoad(Collection $subject, Collection $result): Collection
    {
        if (isset($this->seen[$result]) || !$this->scopeConfig->isSetFlag('samjuk_performance/general/queries')) {
            return $result;
        }
        $this->seen[$result] = true;
        foreach ($result->getItems() as $id => $product) {
            $storeId = (int) $product->getStoreId();
            if ($storeId && $product->getTypeId() === Configurable::TYPE_CODE && !isset($this->counts[$storeId][$id])) {
                $this->pending[$storeId][(int) $id] = $product;
            }
        }
        return $result;
    }

    public function aroundIsSalable(Configurable $subject, callable $proceed, $product)
    {
        $storeId = (int) $product->getStoreId();
        $id = (int) $product->getId();
        if ($subject->getStoreFilter($product) !== null
            || (!isset($this->pending[$storeId][$id]) && !isset($this->counts[$storeId][$id]))
        ) {
            return $proceed($product);
        }
        // AbstractType::isSalable(), which Configurable::isSalable() checks before counting children
        $salable = $product->getStatus() == Status::STATUS_ENABLED;
        if ($salable && $product->hasData('is_salable')) {
            $salable = (bool) (int) $product->getData('is_salable');
        }
        if (!$salable) {
            return false;
        }
        if (isset($this->pending[$storeId][$id])) {
            $this->countPending($storeId);
        }
        return $this->counts[$storeId][$id] !== 0;
    }

    private function countPending(int $storeId): void
    {
        $products = $this->pending[$storeId];
        unset($this->pending[$storeId]);
        // Configurable::getLinkedProductCollection() and isSalable(), for every parent at once
        $collection = $this->collectionFactory->create()->setFlag('product_children', true);
        foreach ($products as $product) {
            $collection->setProductFilter($product);
        }
        $collection->addStoreFilter($storeId);
        // getSize()'s select, grouped per parent
        $select = $this->salableProcessor->process($collection)->getSelectCountSql()
            ->reset(Select::COLUMNS)
            ->columns([
                'parent_id' => 'link_table.parent_id',
                'children' => new \Zend_Db_Expr('COUNT(DISTINCT e.entity_id)'),
            ])
            ->group('link_table.parent_id');
        $byLink = $collection->getConnection()->fetchPairs($select);
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        foreach ($products as $id => $product) {
            $this->counts[$storeId][$id] = (int) ($byLink[$product->getData($linkField)] ?? 0);
        }
    }
}
