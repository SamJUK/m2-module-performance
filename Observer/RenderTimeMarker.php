<?php
declare(strict_types=1);

namespace SamJUK\Performance\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SamJUK\Performance\Plugin\RenderTime;

// Leaves a marker inside the copyright element (Luma/Blank `copyright`, Hyvä `footer-copyright`), before its
// last closing tag, for RenderTime to fill in. Runs after the block cache, so the marker never gets cached in
// block_html.
class RenderTimeMarker implements ObserverInterface
{
    private const BLOCKS = ['copyright', 'footer-copyright'];

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function execute(Observer $observer): void
    {
        if (!in_array($observer->getBlock()->getNameInLayout(), self::BLOCKS, true)
            || !$this->scopeConfig->isSetFlag('samjuk_performance/metrics/footer')
        ) {
            return;
        }
        $transport = $observer->getTransport();
        $html = preg_replace('~(</\w+>\s*)$~', RenderTime::MARKER . '$1', $transport->getHtml(), 1, $count);
        $transport->setHtml($count ? $html : $transport->getHtml() . RenderTime::MARKER);
    }
}
