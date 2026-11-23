<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Config\View;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Config;

// Core parses and merges every view.xml in the theme chain on each request that reads a view var or image size.
class CacheViewConfig
{
    public function __construct(
        private readonly ConfigCache $cache,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Repository $assetRepo,
        private readonly Json $json
    ) {
    }

    /** @param array<string, mixed> $params */
    public function afterGetViewConfig(Config $subject, View $result, array $params = []): View
    {
        // ponytail: bench only, writes View::$data directly; upstream this belongs in View\Config itself
        if ((fn () => $this->data !== null)->call($result)
            || !$this->scopeConfig->isSetFlag('samjuk_performance/general/view_config_cache')
        ) {
            return $result;
        }
        $this->assetRepo->updateDesignParams($params);
        $key = 'SAMJUK_PERFORMANCE_VIEW_CONFIG_'
            . hash('xxh128', $params['themeModel']->getFullPath() . '|' . ($params['area'] ?? ''));
        if ($cached = $this->cache->load($key)) {
            $data = $this->json->unserialize($cached);
        } else {
            $data = $result->read();
            $this->cache->save($this->json->serialize($data), $key, [ConfigCache::CACHE_TAG]);
        }
        (fn () => $this->data = $data)->call($result);
        return $result;
    }
}
