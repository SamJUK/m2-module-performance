<?php
declare(strict_types=1);

namespace SamJUK\Performance\Model;

use Magento\Framework\App\Response\Http;

// Per-request totals for the render-time metrics. Off until dispatch, so the cache decorator never reads config
// while config itself is loading from cache.
class Timings
{
    public bool $enabled = false;

    // Set by the built-in full-page cache plugin: hit, miss or not cacheable
    public ?string $fullPageCache = null;

    /** @var array<string, array{float, int}> name => [ms, calls] */
    private array $metrics = [];

    public function add(string $name, float $ms, int $calls = 1): void
    {
        $this->metrics[$name][0] = ($this->metrics[$name][0] ?? 0) + $ms;
        $this->metrics[$name][1] = ($this->metrics[$name][1] ?? 0) + $calls;
    }

    /** @param array<mixed> $args */
    public function measure(string $name, callable $proceed, array $args): mixed
    {
        if (!$this->enabled) {
            return $proceed(...$args);
        }
        $start = hrtime(true);
        try {
            return $proceed(...$args);
        } finally {
            $this->add($name, (hrtime(true) - $start) / 1e6);
        }
    }

    /** @return array{float, int}|null */
    public function get(string $name): ?array
    {
        return $this->metrics[$name] ?? null;
    }

    // Without the built-in cache (Varnish, or off), PHP only sees misses: cacheable if Varnish may keep it
    public function fullPageCache(Http $response): string
    {
        if ($this->fullPageCache) {
            return $this->fullPageCache;
        }
        $cacheControl = $response->getHeader('Cache-Control');
        return $cacheControl && preg_match('/public.*s-maxage=/', $cacheControl->getFieldValue())
            ? 'miss'
            : 'not cacheable';
    }
}
