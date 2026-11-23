<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

// ProductIdentitiesExtender looks up configurable parents one simple product at a time. This fetches them for
// every simple in a loaded collection with one query, using the same join as the core resource model. Lazily:
// listings load child collections whose parents nobody asks for.
class BatchParentIds
{
    private array $parents = [];
    private array $pending = [];
    private \WeakMap $seen;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ResourceConnection $resource,
        private readonly MetadataPool $metadataPool
    ) {
        $this->seen = new \WeakMap();
    }

    public function afterLoad(Collection $subject, Collection $result): Collection
    {
        // getItems() calls load() again, which comes back through this plugin
        if (isset($this->seen[$result]) || !$this->scopeConfig->isSetFlag('samjuk_performance/general/queries')) {
            return $result;
        }
        $this->seen[$result] = true;
        foreach ($result->getItems() as $id => $product) {
            if ($product->getTypeId() === Type::TYPE_SIMPLE && !isset($this->parents[$id])) {
                $this->pending[(int) $id] = true;
            }
        }
        return $result;
    }

    public function aroundGetParentIdsByChild(Configurable $subject, callable $proceed, $childId)
    {
        if (!is_scalar($childId)) {
            return $proceed($childId);
        }
        if (isset($this->pending[(int) $childId])) {
            $this->fetchPending();
        }
        return $this->parents[(int) $childId] ?? $proceed($childId);
    }

    private function fetchPending(): void
    {
        $ids = array_keys($this->pending);
        $this->pending = [];
        $connection = $this->resource->getConnection();
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $select = $connection->select()
            ->from(['l' => $this->resource->getTableName('catalog_product_super_link')], ['product_id'])
            ->join(
                ['e' => $this->resource->getTableName('catalog_product_entity')],
                "e.$linkField = l.parent_id",
                ['entity_id']
            )
            ->where('l.product_id IN (?)', $ids, \Zend_Db::INT_TYPE);
        foreach ($ids as $id) {
            $this->parents[$id] = [];
        }
        foreach ($connection->fetchAll($select) as $row) {
            $this->parents[(int) $row['product_id']][] = $row['entity_id'];
        }
    }
}
