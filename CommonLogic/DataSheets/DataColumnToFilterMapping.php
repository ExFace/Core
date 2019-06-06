<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnToFilterMappingInterface;

/**
 * Maps on data sheet column to a filter expression in another data sheet.
 * 
 * @see DataColumnMappingInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataColumnToFilterMapping extends DataColumnMapping implements DataColumnToFilterMappingInterface {
    
    private $comparator = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet)
    {
        $fromExpr = $this->getFromExpression();
        $toExpr = $this->getToExpression();
        
        if ($fromExpr->isConstant()){
            $toSheet->getFilters()->addConditionFromExpression($toExpr, $fromExpr->evaluate(), $this->getComparator());
        } elseif ($fromCol = $fromSheet->getColumns()->getByExpression($fromExpr)){
            $separator = $fromExpr->isMetaAttribute() ? $fromExpr->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
            $comparator = $fromSheet->countRows() > 1 ? EXF_COMPARATOR_IN : $this->getComparator();
            $toSheet->getFilters()->addConditionFromExpression($toExpr, implode($separator, $fromCol->getValues(false)), $comparator);
        }
        
        return $toSheet;
    }
    /**
     * @return string $comparator
     */
    public function getComparator()
    {
        return is_null($this->comparator) ? EXF_COMPARATOR_IS : $this->comparator;
    }

    /**
     * @param string $comparator
     * @return DataColumnToFilterMapping
     */
    public function setComparator($comparator)
    {
        $this->comparator = $comparator;
        return $this;
    }

    
}