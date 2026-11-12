<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;

/**
 * Maps sorters over one attribute to sorters over another, preserving their direction.
 *
 * Use in a mapper's `sorter_to_sorter_mappings`:
 *
 * ```json
 * {
 *  "sorter_to_sorter_mappings": [
 *      {"from": "NAME", "to": "TITLE"}
 *  ]
 * }
 * 
 * ```
 *
 * ## Examples
 *
 * Set `to_direction` to `DESC` to replace the incoming direction for a particular attribute.
 */
class DataSorterToSorterMapping extends DataColumnMapping
{
    private ?string $toDirection = null;

    /**
     * Replace the incoming direction with ASC or DESC.
     *
     * @uxon-property to_direction
     * @uxon-type [ASC,DESC]
     * @param string $direction
     * @return DataSorterToSorterMapping
     */
    public function setToDirection(string $direction) : DataSorterToSorterMapping
    {
        $direction = strtoupper($direction);
        if (! in_array($direction, ['ASC', 'DESC'], true)) {
            throw new DataMappingConfigurationError($this, 'Sorter direction must be ASC or DESC.');
        }
        $this->toDirection = $direction;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see DataColumnMapping::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        if (! $this->getFromExpression()->isMetaAttribute() || ! $this->getToExpression()->isMetaAttribute()) {
            throw new DataMappingConfigurationError($this, 'Sorter mappings require attributes on both sides.');
        }
        foreach ($fromSheet->getSorters() as $sorter) {
            if (strcasecmp($sorter->getAttributeAlias(), (string) $this->getFromExpression()) !== 0) {
                continue;
            }
            $direction = $this->toDirection ?? $sorter->getDirection();
            $toSheet->getSorters()->addFromString((string) $this->getToExpression(), $direction);
            $logbook?->addLine('Sorter `' . $this->getFromExpression() . '` -> `' . $this->getToExpression() . '` (' . $direction . ').');
        }
        return $toSheet;
    }

    /**
     * {@inheritDoc}
     * @see DataColumnMapping::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        return [];
    }
}
