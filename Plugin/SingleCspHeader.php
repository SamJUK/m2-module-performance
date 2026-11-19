<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Csp\Api\ModeConfigManagerInterface;
use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\CspRenderer;
use Magento\Csp\Model\Policy\SimplePolicyInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\HttpInterface as HttpResponse;

// SimplePolicyHeaderRenderer re-reads and re-sets the whole header once per policy, and Laminas validates the
// growing ~4.5KB value on every set. Same output as the stock renderer, built once.
class SingleCspHeader
{
    public function __construct(
        private readonly PolicyCollectorInterface $collector,
        private readonly ModeConfigManagerInterface $modeConfig,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function aroundRender(CspRenderer $subject, callable $proceed, HttpResponse $response): void
    {
        if (!$this->scopeConfig->isSetFlag('samjuk_performance/general/csp_single_header')) {
            $proceed($response);
            return;
        }
        $policies = $this->collector->collect();
        foreach ($policies as $policy) {
            if (!$policy instanceof SimplePolicyInterface) {
                $proceed($response);
                return;
            }
        }
        if (!$policies) {
            return;
        }
        $config = $this->modeConfig->getConfigured();
        $header = $config->isReportOnly() ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        $values = [];
        foreach ($policies as $policy) {
            $values[] = $policy->getId() . ' ' . $policy->getValue() . ';';
        }
        if ($config->getReportUri() && !$response->getHeader('Report-To')) {
            $values[0] .= ' report-uri ' . $config->getReportUri() . '; report-to report-endpoint;';
            $response->setHeader('Report-To', json_encode([
                'group' => 'report-endpoint',
                'max_age' => 10886400,
                'endpoints' => [['url' => $config->getReportUri()]],
            ]), true);
        }
        $value = implode(' ', array_reverse($values));
        if ($existing = $response->getHeader($header)) {
            $value .= ' ' . $existing->getFieldValue();
        }
        $response->setHeader($header, $value, true);
    }
}
