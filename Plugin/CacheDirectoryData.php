<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Checkout\CustomerData\DirectoryData;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The directory-data section is the same for every visitor of a store view, but core rebuilds it
 * (every country and region, each translated) on each call. Hyvä embeds it in every uncached page.
 * Tagged CONFIG, so allowed-country changes clear it; direct edits to directory_country_region do not.
 */
class CacheDirectoryData
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResolverInterface $localeResolver,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json
    ) {
    }

    public function aroundGetSectionData(DirectoryData $subject, callable $proceed): array
    {
        if (!$this->scopeConfig->isSetFlag('samjuk_performance/general/directory_data_cache')) {
            return $proceed();
        }
        $key = 'SAMJUK_PERFORMANCE_DIRECTORY_DATA_' . $this->storeManager->getStore()->getId()
            . '_' . $this->localeResolver->getLocale();
        if ($cached = $this->cache->load($key)) {
            return $this->json->unserialize($cached);
        }
        $data = $proceed();
        $this->cache->save($this->json->serialize($data), $key, [ConfigCache::CACHE_TAG]);
        return $data;
    }
}
