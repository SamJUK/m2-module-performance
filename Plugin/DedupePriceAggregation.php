<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Elasticsearch\SearchAdapter\Dynamic\DataProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Search\Dynamic\EntityStorage;
use Magento\Framework\Search\Request\BucketInterface;

// Dynamic\Algorithm\Auto::getRange() queries the histogram for the range it settles on, then getItems() queries
// the same range again. Keeps the EntityStorage referenced so its object id can't be reused this request.
class DedupePriceAggregation
{
    private array $results = [];

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function aroundGetAggregation(
        DataProvider $subject,
        callable $proceed,
        BucketInterface $bucket,
        array $dimensions,
        $range,
        EntityStorage $entityStorage
    ) {
        if (!$this->scopeConfig->isSetFlag('samjuk_performance/general/price_aggregation_dedupe')) {
            return $proceed($bucket, $dimensions, $range, $entityStorage);
        }
        $key = spl_object_id($entityStorage) . '|' . $bucket->getName() . '|' . $range;
        $this->results[$key] ??= [$entityStorage, $proceed($bucket, $dimensions, $range, $entityStorage)];
        return $this->results[$key][1];
    }
}
