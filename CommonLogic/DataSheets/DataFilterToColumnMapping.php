<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\DataSheets\DataFilterToColumnMappingInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;

/**
 * Maps all filters matching the given expression from one sheet to a column of another sheet.
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
                if ($comparator === $ccomp
                    || (($comparator === null || $comparator === '') && ($ccomp === ComparatorDataType::EQUALS || $ccomp === ComparatorDataType::IS || $ccomp === ComparatorDataType::IN))) {
                    $result[] = $condition;
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
}