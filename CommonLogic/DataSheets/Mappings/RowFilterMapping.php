<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Removes rows by appying a filter condition group.
 * 
 * This mapping applies either to the to-sheet of the mapper (default) or to the from-sheet.
 * This can be controlled using `apply_to`. Applying it to the to-sheet can be used to
 * "clea up" data removing meaningless rows. Applying the filter to the from-sheet allows to
 * exclude rows from subsequent mappings.
 * 
 * Depending on the `mode` setting, the filter will either keep matching rows only or remove
 * them.
 * 
 * ## Examples
 * 
 * ### Remove rows with empty values in at least one of the listed columns
 * 
 * ```
 *  {
 *      "input_mapper": {
 *          "row_filter": {
 *              "mode": "keep_matches_only",
 *              "filter": {
 *                  "operator": "OR",
 *                  "conditions": [
 *                    { "expression": "COLUMN_1", "comparator": "!==", "value": "" },
 *                    { "expression": "COLUMN_2", "comparator": "!==", "value": "" }
 *                 ]
 *              }
 *          }
 *      }
 *  }
 *    
 * ```        
 * 
 * @author Andrej Kabachnik
 *
 */
class RowFilterMapping extends AbstractDataSheetMapping 
{
    const APPLY_TO_FROM_SHEEET = 'from-sheet';
    const APPLY_TO_TO_SHEET = 'to-sheet';
    const MODE_KEEP_MATCHES = 'keep_matches_only';
    const MODE_REMOVE_MATCHES = 'remove_matches';
    
    private $conditionGroupUxon = null;
    
    private $applyTo = self::APPLY_TO_TO_SHEET;
    
    private $mode = self::MODE_KEEP_MATCHES;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet)
    {
        switch ($this->getApplyTo()) {
            case self::APPLY_TO_FROM_SHEEET:
                $condGrp = $this->getConditionGroup($fromSheet->getMetaObject());
                $sheetToFilter = $fromSheet;
                break;
            case self::APPLY_TO_TO_SHEET:
                $condGrp = $this->getConditionGroup($toSheet->getMetaObject());
                $sheetToFilter = $toSheet;
                break;
            default:
                throw new DataMappingConfigurationError($this, 'Invalid data mapping configuration: "' . $this->getApplyTo() . '" is not a valid value for `apply_to`');
        }
        
        switch ($this->getMode()) {
            case self::MODE_KEEP_MATCHES:
                $diffSheet = $sheetToFilter->extract($condGrp);
                break;
            case self::MODE_REMOVE_MATCHES:
                $nonMatchedRows = $sheetToFilter->getRowsDiff($sheetToFilter->extract($condGrp));
                $diffSheet = $sheetToFilter->copy()->removeRows();
                $diffSheet->addRows($nonMatchedRows, false, false);
                break;
            default:
                throw new DataMappingConfigurationError($this, 'Invalid data mapping configuration: "' . $this->getApplyTo() . '" is not a valid value for `mode`');
        }
        
        if ($this->getApplyTo() === self::APPLY_TO_TO_SHEET) {
            $toSheet = $diffSheet;
        } else {
            $fromSheet->removeRows();
            $fromSheet->importRows($diffSheet, false);
        }
        
        return $toSheet;
    }
    
    /**
     * 
     * @return ConditionGroupInterface
     */
    protected function getConditionGroup(MetaObjectInterface $baseObject) : ConditionGroupInterface
    {
        return ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->getConditionGroupUxon(), $baseObject);
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
        if ($this->getApplyTo() === self::APPLY_TO_FROM_SHEEET) {
            $obj = $this->getMapper()->getFromMetaObject();
        } else {
            $obj = $this->getMapper()->getToMetaObject();
        }
        foreach ($this->getConditionGroup($obj)->getConditionsRecursive() as $cond) {
            if (! $cond->getLeftExpression()->isStatic()) {
                $exprs[] = $cond->getLeftExpression();
            }
        }
        return $exprs;
    }
    
    /**
     * 
     * @return string
     */
    protected function getMode() : string
    {
        return $this->mode;
    }
    
    /**
     * Keep the rows, that match the filters (default), or remove them
     * 
     * @uxon-property mode
     * @uxon-type [keep_matches_only,remove_matches]
     * @uxon-default keep_matches_only
     * 
     * @param string $value
     * @return RowFilterMapping
     */
    protected function setMode(string $value) : RowFilterMapping
    {
        $this->mode = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getApplyTo() : string
    {
        return $this->applyTo;
    }
    
    /**
     * Filter the to-sheet (default) or the from-sheet
     *
     * @uxon-property apply_to
     * @uxon-type [to-sheet,from-sheet]
     * @uxon-default to-sheet
     *
     * @param string $value
     * @return RowFilterMapping
     */
    protected function setApplyTo(string $value) : RowFilterMapping
    {
        $this->applyTo = $value;
        return $this;
    }
}