<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin\Timing;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use SamJUK\Performance\Model\Timings;

// Outermost dispatch plugin (sortOrder in di.xml), so a built-in full-page cache hit is timed too. Everything
// before it counts as boot. Queries are timed by the stock Zend profiler from here on, which costs nothing when off.
class Dispatch
{
    public function __construct(
        private readonly Timings $timings,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ResourceConnection $resource
    ) {
    }

    /** @return ResponseInterface|ResultInterface */
    public function aroundDispatch(FrontControllerInterface $subject, callable $proceed, RequestInterface $request)
    {
        $this->timings->enabled = $this->scopeConfig->isSetFlag('samjuk_performance/metrics/server_timing')
            || $this->scopeConfig->isSetFlag('samjuk_performance/metrics/footer');
        if (!$this->timings->enabled) {
            return $proceed($request);
        }
        /** @var HttpRequest $request */
        $this->timings->add('boot', (microtime(true) - $request->getServer('REQUEST_TIME_FLOAT')) * 1000);
        /** @var Mysql $connection */
        $connection = $this->resource->getConnection();
        $connection->setProfiler(new \Zend_Db_Profiler(true));

        $start = hrtime(true);
        $result = $proceed($request);
        // Legacy controllers (search results, CMS) call renderLayout() themselves, so render can happen in here.
        $this->timings->add('dispatch', (hrtime(true) - $start) / 1e6 - ($this->timings->get('render')[0] ?? 0));
        return $result;
    }
}
