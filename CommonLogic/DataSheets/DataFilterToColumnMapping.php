<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\DataSheets\DataFilterToColumnMappingInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Uxon\DataSheetMapperSchema;

/**
 * Maps all filters matching the given expression from one sheet to a column of another sheet.
 * 
 * This mapping searches all filter conditions of the input data sheet for those, where the
 * right side matches the from-expression and puts their values into a (new) column of the
 * to-sheet, that is created via the to-expression.
 * 
 * By setting the mapping-properties `to_single_row` and `to_single_row_separator` you can
 * make all values concatennated into a single value, that will be put in the first row of
 * the to-sheet.
 * 
 * By default, all filter conditions (even those in nested condition groups) are used as
 * source for the mapping. However, if `from_comparator` is provided, only those filters
 * matching this comparator will be used.
 * 
 * Multi-select filters yield multiple values resulting in multiple rows in the to-sheet
 * unless `to_single_row` is set to `true`.
 * 
 * If `inherit_filters` is set in the mapper, matching filters will NOT be inherited by
 * defualt (because they are transformed to columns). If you want them to get inherited,
 * set `prevent_inheriting_filter` to `false` for this mapping.
 * 
 * @see DataColumnMappingInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataFilterToColumnMapping extends DataColumnMapping implements DataFilterToColumnMappingInterface 
{
    private $comparator = null;
    
    private $toSingleRowSeparator = null;
    
    private $toSingleRow = false;
    
    private $removeFilter = true;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet)
    {
        $fromExpr = $this->getFromExpression();
        $toExpr = $this->getToExpression();
        
        $conditions = $this->findFilterConditions($fromSheet->getFilters(), $fromExpr, $this->getFromComparator());
        $values = [];
        foreach ($conditions as $cond) {
            /* @var $cond \exface\Core\Interfaces\Model\ConditionInterface */
            if ($cond->getValue() === '' || $cond->getValue() === null) {
                continue;
            }
            if ($cond->getComparator() === ComparatorDataType::IN) {
                if (is_array($cond->getValue()) === true) {
                    $condVals = $cond->getValue();
                } else {
                    $delim = $cond->getExpression()->isMetaAttribute() ? $cond->getExpression()->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
                    $condVals = explode($delim, $cond->getValue());
                }
                $values = array_merge($values, $condVals);
            } else {
                $values[] = $cond->getValue();
            }
        }
        
        if (empty($values) === true) {
            return $toSheet;
        }
        
        $values = array_unique($values);
        
        if ($this->getToSingleRow() === true) {
            if ($this->getToSingleRowSeparator() !== null) {
                $separator = $this->getToSingleRowSeparator();
            } elseif ($toExpr->isMetaAttribute() === true) {
                $separator = $toExpr->getAttribute()->getValueListDelimiter();
            } else {
                $separator = EXF_LIST_SEPARATOR;
            }
            $values = [implode($separator, $values)];
        }
        
        $toSheet->getColumns()->addFromExpression($toExpr)->setValues($values);
        
        return $toSheet;
    }
    
    /**
     * 
     * @param DataSheetInterface $fromSheet
     * @param ExpressionInterface $fromExpression
     * @param string $comparator
     * @return ConditionInterface[]
     */
    protected function findFilterConditions(ConditionGroupInterface $fromConditionGroup, ExpressionInterface $fromExpression, string $comparator = null) : array
    {
        $exprString = $fromExpression->toString();
        $result = [];
        
        foreach ($fromConditionGroup->getConditions() as $condition) {
            $ccomp = $condition->getComparator();
            if (strcasecmp($condition->getExpression()->toString(), $exprString) === 0) {
                if ($comparator === $ccomp || ($comparator === null || $comparator === '')) {
                    $result[] = $condition;
                    if ($this->getPreventInheritingFilter()) {
                        $fromConditionGroup->removeCondition($condition);
                    }
                }
            }
        }
        
        foreach ($fromConditionGroup->getNestedGroups() as $group) {
            $result = array_merge($result, $this->findFilterConditions($group, $fromExpression, $comparator));
        }
        
        return $result;
    }
    
    /**
     * @return string|NULL $comparator
     */
    protected function getFromComparator() : ?string
    {
        return is_null($this->comparator) ? EXF_COMPARATOR_IS : $this->comparator;
    }

    /**
     * Take only filters with this comparator.
     * 
     * @uxon-property comparator
     * @uxon-type metamodel:comparator
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataFilterToColumnMappingInterface::setFromComparator()
     */
    public function setFromComparator(string $comparator) : DataFilterToColumnMappingInterface
    {
        $this->comparator = $comparator;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    protected function getToSingleRow() : bool
    {
        return $this->toSingleRow;
    }
    
    /**
     * Set to TRUE to concatenate all values into a single row in the resulting column using the `to_single_row_separator`
     * 
     * @uxon-property to_single_row
     * @uxon-type bool
     * @uxon-default false
     * 
     * @param bool $value
     * @return DataFilterToColumnMappingInterface
     */
    public function setToSingleRow(bool $value) : DataFilterToColumnMappingInterface
    {
        $this->toSingleRow = $value;
        return $this;
    }
    
    /**
     *
     * @return string|null
     */
    protected function getToSingleRowSeparator() : ?string
    {
        return $this->toSingleRowSeparator;
    }
    
    /**
     * A separator to concatenate all values into a single row in the resulting column.
     * 
     * If set, `to_single_row` is automatically assumed to be `true`.
     * 
     * @uxon-property to_single_row_separator
     * @uxon-type string
     * 
     * @param string $value
     * @return DataFilterToColumnMappingInterface
     */
    public function setToSingleRowSeparator(string $value) : DataFilterToColumnMappingInterface
    {
        $this->toSingleRowSeparator = $value;
        $this->toSingleRow = true;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getPreventInheritingFilter() : bool
    {
        return $this->removeFilter;
    }
    
    /**
     * Set to FALSE if you want the to-sheet to inherit the filter if possible.
     * 
     * @uxon-property prevent_inheriting_filter
     * @uxon-type boolean
     * @uxon-default true     * 
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataFilterToColumnMappingInterface::setPreventInheritingFilter()
     */
    public function setPreventInheritingFilter(bool $value) : DataFilterToColumnMappingInterface
    {
        $this->removeFilter = $value;
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