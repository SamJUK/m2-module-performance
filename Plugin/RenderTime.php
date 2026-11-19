<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http;
use SamJUK\Performance\Model\Timings;

// Time from PHP accepting the request to sending the response, with the breakdown from Timings. Varnish caches it
// with the page, so a cached page shows its miss. The built-in full-page cache fills it in on every response.
class RenderTime
{
    public const MARKER = '<!--samjuk-render-time-->';

    // Boot, dispatch and render add up to about the total. The rest happen inside them.
    private const LABELS = [
        'boot' => 'Boot',
        'dispatch' => 'Routing and controller',
        'render' => 'Render',
        'layout' => 'Layout build',
        'db' => 'Database',
        'cache' => 'Cache',
        'search' => 'Search',
    ];
    private const PARTS = [
        'layout' => null,
        'db' => ['query', 'queries'],
        'cache' => ['call', 'calls'],
        'search' => ['call', 'calls'],
    ];

    private const FULL_PAGE_CACHE = ['hit' => 'FPC hit', 'miss' => 'FPC miss', 'not cacheable' => 'Not cacheable'];

    private const STYLE = '<style>'
        . '.samjuk-render-time{float:right;margin-left:auto;position:relative;text-align:left}'
        . '.samjuk-render-time summary{cursor:pointer;text-decoration:underline dotted}'
        . '.samjuk-render-time table{position:absolute;right:0;bottom:calc(100% + 8px);z-index:100;width:auto;'
        . 'margin:0;padding:8px 12px;border-collapse:separate;border-spacing:0;background:#111827;color:#f9fafb;'
        . 'border:1px solid #374151;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.35);font-size:12px;line-height:1.8;'
        . 'white-space:nowrap;font-variant-numeric:tabular-nums}'
        . '.samjuk-render-time th,.samjuk-render-time td{padding:0 0 0 16px;border:0;background:none;'
        . 'font-weight:400;text-align:right}'
        . '.samjuk-render-time th{padding:0;text-align:left}'
        . '.samjuk-render-time .n,.samjuk-render-time .part th{color:#9ca3af}'
        . '.samjuk-render-time .fpc{white-space:nowrap}'
        . '.samjuk-render-time .part th{padding-left:12px}'
        . '.samjuk-render-time tr:not(.part)+.part>*,.samjuk-render-time .mem>*{border-top:1px solid #374151}'
        . '</style>';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RequestInterface $request,
        private readonly Timings $timings,
        private readonly ResourceConnection $resource
    ) {
    }

    public function beforeSendResponse(Http $subject): void
    {
        $total = (microtime(true) - $this->request->getServer('REQUEST_TIME_FLOAT')) * 1000;
        $ms = sprintf('%.1f', $total);
        $metrics = $this->metrics();
        $memory = sprintf('%.1fMB', memory_get_peak_usage() / 1048576);
        $fpc = $this->timings->enabled ? self::FULL_PAGE_CACHE[$this->timings->fullPageCache($subject)] : null;

        if ($this->scopeConfig->isSetFlag('samjuk_performance/metrics/server_timing')) {
            $entries = ["app;desc=\"PHP total\";dur=$ms"];
            foreach ($metrics as $name => [$label, $time, $calls]) {
                $desc = $calls === null ? $label : "$label ($calls)";
                $entries[] = sprintf('%s;desc="%s";dur=%.1f', $name, $desc, $time);
            }
            $entries[] = "mem;desc=\"Peak memory $memory\"";
            if ($fpc) {
                $entries[] = "fpc;desc=\"$fpc\"";
            }
            $subject->setHeader('Server-Timing', implode(', ', $entries));
        }
        if ($this->scopeConfig->isSetFlag('samjuk_performance/metrics/header')) {
            $subject->setHeader('X-Render-Time', "{$ms}ms", true);
        }
        $body = $subject->getContent();
        if (is_string($body) && str_contains($body, self::MARKER)) {
            $subject->setContent(str_replace(self::MARKER, $this->footer($ms, $metrics, $memory, $fpc), $body));
        }
    }

    /** @return array<string, array{string, float, ?string}> name => [label, ms, calls] */
    private function metrics(): array
    {
        if (!$this->timings->enabled) {
            return [];
        }
        $profiler = $this->resource->getConnection()->getProfiler();
        $this->timings->add('db', $profiler->getTotalElapsedSecs() * 1000, $profiler->getTotalNumQueries());

        $metrics = [];
        foreach (self::LABELS as $name => $label) {
            if ($metric = $this->timings->get($name)) {
                $units = self::PARTS[$name] ?? null;
                $metrics[$name] = [$label, $metric[0], $units ? "$metric[1] " . $units[$metric[1] === 1 ? 0 : 1] : null];
            }
        }
        if (isset($metrics['cache'])) {
            $misses = $this->timings->get('cache_miss')[1] ?? 0;
            $metrics['cache'][2] .= sprintf(', %d %s', $misses, $misses === 1 ? 'miss' : 'misses');
        }
        return $metrics;
    }

    private function footer(string $ms, array $metrics, string $memory, ?string $fpc): string
    {
        $rows = '';
        foreach ($metrics as $name => [$label, $time, $calls]) {
            $rows .= sprintf(
                '<tr%s><th>%s</th><td class="n">%s</td><td>%.1fms</td></tr>',
                array_key_exists($name, self::PARTS) ? ' class="part"' : '',
                $label,
                $calls ?? '',
                $time
            );
        }
        $rows .= "<tr class=\"mem\"><th>Peak memory</th><td></td><td>$memory</td></tr>";
        $fpc = $fpc ? " <span class=\"fpc\">($fpc)</span>" : '';

        return ' ' . self::STYLE . '<details class="samjuk-render-time">'
            . "<summary>Rendered&nbsp;in&nbsp;{$ms}ms$fpc</summary><table>$rows</table></details>";
    }
}
