<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnToFilterMappingInterface;
use exface\Core\Uxon\DataSheetMapperSchema;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Interfaces\Debug\LogBookInterface;

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
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $fromExpr = $this->getFromExpression();
        $toExpr = $this->getToExpression();
        $log = "Column `{$fromExpr->__toString()}` -> filter ";
        
        switch (true) {
            case $fromExpr->isStatic():
                $val = $fromExpr->evaluate();
                $log .= "`{$toExpr->__toString()} {$this->getComparator()} {$val}`.";
                $toSheet->getFilters()->addConditionFromExpression($toExpr, $val, $this->getComparator());
                break;
            case $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr):
                $separator = $fromExpr->isMetaAttribute() ? $fromExpr->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
                $comparator = $fromSheet->countRows() > 1 ? EXF_COMPARATOR_IN : $this->getComparator();
                $value = implode($separator, $fromCol->getValues(false));
                $log .= "`{$toExpr->__toString()} {$comparator} {$value}`.";
                $toSheet->getFilters()->addConditionFromExpression($toExpr, $value, $comparator);
                break;
            // If not enough data, but explicitly configured to ignore it, exit here
            case $this->getIgnoreIfMissingFromColumn() === true && ($fromExpr->isMetaAttribute() || $fromExpr->isFormula() || $fromExpr->isUnknownType()):
                if ($logbook !== null) $logbook->addLine($log . ' Ignored because `ignore_if_missing_from_column` is `true` and not from-data was found.');
                return $toSheet;
            default:
                if ($fromExpr->isMetaAttribute()) {
                    throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot map from attribute "' . $fromExpr->toString() . '" in a column-to-filter mapping: there is no matching column in the from-data and it cannot be loaded automatically (e.g. because the from-object ' . $fromSheet->getMetaObject() .' has no UID attribute)!', '7H6M243');
                }
                throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot use "' . $fromExpr->toString() . '" as from-expression in a column-to-filter mapping: only data column names, constants and static formulas allowed!', '7H6M243');
        }
        
        if ($logbook !== null) $logbook->addLine($log);
        
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