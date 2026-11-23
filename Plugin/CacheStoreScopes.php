<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\App\Config\Source\RuntimeConfigSource;

// Core reads store_website, store_group and store uncached on every request. Store and website saves
// clean the config cache; store group saves don't, so CleanStoreScopes covers them. No toggle: store
// config is resolved from these rows, so it can't switch this off.
class CacheStoreScopes
{
    public const KEY = 'SAMJUK_PERFORMANCE_STORE_SCOPES';

    public function __construct(
        private readonly ConfigCache $cache,
        private readonly Json $json
    ) {
    }

    /**
     * @param string $path
     * @return array<mixed>
     */
    public function aroundGet(RuntimeConfigSource $subject, callable $proceed, $path = ''): array
    {
        if ($cached = $this->cache->load(self::KEY)) {
            return $this->json->unserialize($cached);
        }
        $data = $proceed($path);
        if ($data) {
            $this->cache->save($this->json->serialize($data), self::KEY, [ConfigCache::CACHE_TAG]);
        }
        return $data;
    }
}
