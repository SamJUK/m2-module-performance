<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin\Timing;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\SearchEngineInterface;
use SamJUK\Performance\Model\Timings;

// Engine agnostic: every catalog search and layered navigation request goes through SearchEngineInterface.
class Search
{
    public function __construct(private readonly Timings $timings)
    {
    }

    public function aroundSearch(SearchEngineInterface $subject, callable $proceed, RequestInterface $request)
    {
        return $this->timings->measure('search', $proceed, [$request]);
    }
}
