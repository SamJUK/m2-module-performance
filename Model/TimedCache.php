<?php
declare(strict_types=1);

namespace SamJUK\Performance\Model;

use Magento\Framework\Cache\Frontend\Decorator\Bare;
use Magento\Framework\Cache\FrontendInterface;

// Wraps every cache frontend the factory builds, built-in full-page cache included. A decorator because a plugin
// on a cache frontend recurses: the plugin list loads from the cache it is intercepting.
class TimedCache extends Bare
{
    // Mage-OS applies decorators twice (createSymfonyCache() and create()), so only the inner copy times
    private readonly bool $outer;

    public function __construct(FrontendInterface $frontend, private readonly Timings $timings)
    {
        parent::__construct($frontend);
        $this->outer = $frontend instanceof self;
    }

    public function load($identifier)
    {
        if ($this->outer) {
            return parent::load($identifier);
        }
        $result = $this->timings->measure('cache', parent::load(...), [$identifier]);
        if ($result === false && $this->timings->enabled) {
            $this->timings->add('cache_miss', 0);
        }
        return $result;
    }

    /** @param string[] $tags */
    public function save($data, $identifier, array $tags = [], $lifeTime = null)
    {
        if ($this->outer) {
            return parent::save($data, $identifier, $tags, $lifeTime);
        }
        return $this->timings->measure('cache', parent::save(...), [$data, $identifier, $tags, $lifeTime]);
    }
}
