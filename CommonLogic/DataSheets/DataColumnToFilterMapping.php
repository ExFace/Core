<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnToFilterMappingInterface;
use exface\Core\Uxon\DataSheetMapperSchema;
use exface\Core\Exceptions\DataSheets\DataSheetMapperError;

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
        
        switch (true) {
            case $fromExpr->isStatic():
                $toSheet->getFilters()->addConditionFromExpression($toExpr, $fromExpr->evaluate(), $this->getComparator());
                break;
            case $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr):
                $separator = $fromExpr->isMetaAttribute() ? $fromExpr->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
                $comparator = $fromSheet->countRows() > 1 ? EXF_COMPARATOR_IN : $this->getComparator();
                $toSheet->getFilters()->addConditionFromExpression($toExpr, implode($separator, $fromCol->getValues(false)), $comparator);
                break;
            default:
                if ($fromExpr->isMetaAttribute()) {
                    throw new DataSheetMapperError($this->getMapper(), 'Cannot map from attribute "' . $fromExpr->toString() . '" in a column-to-filter mapping: there is no matching column in the from-data and it cannot be loaded automatically (e.g. because the from-object ' . $fromSheet->getMetaObject() .' has no UID attribute)!');
                }
                throw new DataSheetMapperError($this->getMapper(), 'Cannot use "' . $fromExpr->toString() . '" as from-expression in a column-to-filter mapping: only data column names, constants and static formulas allowed!');
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
     * Use this comparator in the resulting filter.
     * 
     * @uxon-property comparator
     * @uxon-type metamodel:comparator
     * 
     * @param string $comparator
     * @return DataColumnToFilterMapping
     */
    public function setComparator($comparator)
    {
        $this->comparator = $comparator;
        return $this;
    }
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return DataSheetMapperSchema::class;
    }
}