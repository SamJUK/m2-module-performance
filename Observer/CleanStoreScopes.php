<?php
declare(strict_types=1);

namespace SamJUK\Performance\Observer;

use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SamJUK\Performance\Plugin\CacheStoreScopes;

class CleanStoreScopes implements ObserverInterface
{
    public function __construct(private readonly ConfigCache $cache)
    {
    }

    public function execute(Observer $observer): void
    {
        $this->cache->remove(CacheStoreScopes::KEY);
    }
}
