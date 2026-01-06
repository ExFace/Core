<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Replaces placeholders by filter values from the given data sheet
 * 
 * Placeholders are matched against the `expression` of every filter condition.
 * 
 * ## Examples
 * 
 * TODO
 * 
 * @author Andrej Kabachnik
 */
class DataFiltersPlaceholders extends AbstractPlaceholderResolver
{
    private DataSheetInterface $dataSheet;

    /**
     *
     * @param DataSheetInterface $dataSheet
     * @param string $prefix
     */
    public function __construct(DataSheetInterface $dataSheet, string $prefix = "~filter:")
    {
        $this->setPrefix($prefix);
        $this->dataSheet = $dataSheet;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {
        $phs = $this->filterPlaceholders($placeholders);
        $phVals = [];
        if (empty($phs)) {
            return $phVals;
        }
        $conditions = $this->dataSheet->getFilters()->getConditions();
        foreach ($phs as $ph) {
            $expr = $this->stripPrefix($ph);
            foreach ($conditions as $condition) {
                if ($condition->getAttributeAlias() === $expr) {
                    $phVals[$ph] = $condition->getValue();
                }
            }
        }
        return $phVals;
    }
}