<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Swatches\Helper\Data as SwatchHelper;

// With a swatch filter in the URL, a listing swaps each configurable's image for its matching child, and finds and
// fully loads that child (repository, about 16 queries) one product at a time. The image only needs the image
// attributes, so this finds the children for the whole listing in one query and loads just those attributes.
class BatchSwatchImageVariations
{
    private const IMAGE_ATTRIBUTES = ['name', 'image', 'small_image', 'thumbnail', 'image_label', 'small_image_label', 'thumbnail_label'];

    private ?ListProduct $listing = null;
    /** @var array<string, ProductInterface|false> filters|parent id => child */
    private array $children = [];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CollectionFactory $collectionFactory,
        private readonly MetadataPool $metadataPool
    ) {
    }

    // sortOrder below Swatches' ProductImage, so its beforeGetImage() runs inside this
    public function aroundGetImage(AbstractProduct $subject, callable $proceed, ...$args)
    {
        if (!$subject instanceof ListProduct || !$this->scopeConfig->isSetFlag('samjuk_performance/general/queries')) {
            return $proceed(...$args);
        }
        $this->listing = $subject;
        try {
            return $proceed(...$args);
        } finally {
            $this->listing = null;
        }
    }

    public function aroundLoadVariationByFallback(SwatchHelper $subject, callable $proceed, ProductInterface $parent, array $attributes)
    {
        $products = $this->listing?->getLoadedProductCollection()->getItems() ?? [];
        if (!isset($products[$parent->getId()])) {
            return $proceed($parent, $attributes);
        }
        $key = json_encode($attributes) . '|' . $parent->getId();
        if (!array_key_exists($key, $this->children)) {
            $this->resolve($subject, $products, $attributes);
        }
        return $this->children[$key];
    }

    private function resolve(SwatchHelper $helper, array $products, array $attributes): void
    {
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $groups = [];
        foreach ($products as $id => $product) {
            $key = json_encode($attributes) . '|' . $id;
            if ($product->getTypeId() !== Configurable::TYPE_CODE || array_key_exists($key, $this->children)) {
                continue;
            }
            $this->children[$key] = false;
            if (!$helper->isProductHasSwatch($product)) {
                continue;
            }
            // Helper\Data::loadVariationByFallback() filters by the requested attributes the parent is configured by
            $filters = [];
            foreach ($helper->getAttributesFromConfigurable($product) as $attribute) {
                $code = $attribute->getData('attribute_code');
                if (array_key_exists($code, $attributes)) {
                    $filters[$code] = $attributes[$code];
                }
            }
            $groups[json_encode($filters)][$product->getData($linkField)] = $id;
        }

        foreach ($groups as $filters => $parents) {
            // load(), not a raw select, so the frontend stock filter applies as it does in core
            $collection = $this->collectionFactory->create()->addAttributeToSelect(self::IMAGE_ATTRIBUTES);
            $relation = $collection->getTable('catalog_product_relation');
            $collection->getSelect()->distinct()
                ->join(['pr' => $relation], 'e.entity_id = pr.child_id', [])
                ->where('pr.parent_id IN (?)', array_keys($parents), \Zend_Db::INT_TYPE)
                ->order('e.entity_id');
            foreach (json_decode($filters, true) as $code => $option) {
                $collection->addAttributeToFilter($code, ['eq' => $option]);
            }
            $children = $collection->getItems();
            if (!$children) {
                continue;
            }
            $connection = $collection->getConnection();
            $links = $connection->fetchAll($connection->select()->from($relation, ['parent_id', 'child_id'])
                ->where('parent_id IN (?)', array_keys($parents), \Zend_Db::INT_TYPE)
                ->where('child_id IN (?)', array_keys($children), \Zend_Db::INT_TYPE)
                ->order('child_id'));
            foreach ($links as $link) {
                $key = json_encode($attributes) . '|' . $parents[$link['parent_id']];
                $this->children[$key] = $this->children[$key] ?: $children[$link['child_id']];
            }
        }
    }
}
