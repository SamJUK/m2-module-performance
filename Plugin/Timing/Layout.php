<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin\Timing;

use Magento\Framework\View\Layout\Builder;
use Magento\Framework\View\LayoutInterface;
use SamJUK\Performance\Model\Timings;

// Layout XML load and merge, and block generation. Runs inside dispatch or render, whichever touches the layout first.
class Layout
{
    public function __construct(private readonly Timings $timings)
    {
    }

    /** @return LayoutInterface */
    public function aroundBuild(Builder $subject, callable $proceed)
    {
        return $this->timings->measure('layout', $proceed, []);
    }
}
