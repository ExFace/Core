<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionGroupFactory;

/**
 * Removes any rows not matching the provided condition group
 * 
 * ## Examples
 * 
 * ### Remove rows with empty values in at least on of the listed columns
 * 
 * TODO
 * 
 * @author Andrej Kabachnik
 *
 */
class RowFilterMapping extends AbstractDataSheetMapping 
{
    private $conditionGroupUxon = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet)
    {
        return $toSheet->extract($this->getConditionGroup());
    }
    
    /**
     * 
     * @return ConditionGroupInterface
     */
    protected function getConditionGroup() : ConditionGroupInterface
    {
        return ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->getConditionGroupUxon(), $this->getMapper()->getToMetaObject());
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getConditionGroupUxon() : UxonObject
    {
        return $this->conditionGroupUxon;
    }
    
    /**
     * The condition group for filtering - rows, that do not match it, will be removed
     * 
     * @uxon-property filter
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-required true
     * @uxon-template {"operator": "AND", "conditions": [{"expression": "","comparator": "==","value": ""}]}
     * 
     * @param UxonObject $uxon
     * @return RowFilterMapping
     */
    protected function setFilter(UxonObject $uxon) : RowFilterMapping
    {
        $this->conditionGroupUxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        $exprs = [];
        foreach ($this->getConditionGroup()->getConditionsRecursive() as $cond) {
            if (! $cond->getLeftExpression()->isStatic()) {
                $exprs[] = $cond->getLeftExpression();
            }
        }
        return $exprs;
    }
}