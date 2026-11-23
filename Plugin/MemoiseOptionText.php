<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\App\Config\ScopeConfigInterface;

// getOptionText() queries the option table on every call: a PDP asks for the same attributes twice, and asks
// for attributes the product doesn't have (option_id IN ('') never matches).
class MemoiseOptionText
{
    /** @var array<string, array<mixed>> */
    private array $options = [];

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * @param string|array<int|string>|null $ids
     * @param bool $withEmpty
     * @return array<mixed>
     */
    public function aroundGetSpecificOptions(Table $subject, callable $proceed, $ids, $withEmpty = true)
    {
        if (!$this->scopeConfig->isSetFlag('samjuk_performance/general/queries')) {
            return $proceed($ids, $withEmpty);
        }
        if (!$withEmpty && ($ids === null || $ids === '' || $ids === [])) {
            return [];
        }
        $attribute = $subject->getAttribute();
        $key = $attribute->getId() . '|' . $attribute->getStoreId()
            . '|' . implode(',', (array) $ids) . '|' . (int) $withEmpty;
        return $this->options[$key] ??= $proceed($ids, $withEmpty);
    }
}
