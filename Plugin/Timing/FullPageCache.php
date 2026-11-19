<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin\Timing;

use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\Response\Http;
use SamJUK\Performance\Model\Timings;

// The built-in full-page cache. Cache-Control is read here because process() replaces it with no-cache headers.
class FullPageCache
{
    public function __construct(private readonly Timings $timings)
    {
    }

    public function afterLoad(Kernel $subject, $result)
    {
        if ($result !== false) {
            $this->timings->fullPageCache = 'hit';
        }
        return $result;
    }

    public function beforeProcess(Kernel $subject, Http $response): void
    {
        $this->timings->fullPageCache = $this->timings->fullPageCache($response);
    }
}
