<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin\Timing;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use SamJUK\Performance\Model\Timings;

class Render
{
    public function __construct(private readonly Timings $timings)
    {
    }

    public function aroundRenderResult(ResultInterface $subject, callable $proceed, ResponseInterface $response)
    {
        return $this->timings->measure('render', $proceed, [$response]);
    }
}
