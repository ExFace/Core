<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;

/**
 * Fills a column of the to-sheet with values from a context variable.
 * 
 * If the variable is an array, each element will be applied to the respective row. If the number of
 * array elements does not match the number of rows, an error will be raised.
 * 
 * Scalar variables (strings, numbers, etc.) will be applied to all rows of the to-column.
 * 
 * This is basically the reverse of `column_to_variable` mappings.
 * 
 * @author Andrej Kabachnik
 *
 */
class VariableToColumnMapping extends AbstractDataSheetMapping
{
    private $varName = null;
    
    private $toExpression = null;
    
    private $duplicateRows = false;
    
    private $createRowInEmptyData = true;
    
    /**
     * 
     * @return string
     */
    public function getVariableName() : string
    {
        return $this->varName;
    }
    
    /**
     * Any use of this expression in the data sheet will be transformed to the to-expression in the mapped sheet.
     * 
     * The expression can be an attribute alias, a constant or a formula.
     * 
     * @uxon-property variable
     * @uxon-type string
     * @uxon-required true
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::setFrom()
     */
    public function setVariable(string $string) : VariableToColumnMapping
    {
        $this->varName = $string;
        return $this;
    }

    

    /**
     * Same as `variable` - same as `from` - just to make moving typical mappings between types easier.
     * 
     * @uxon-property from
     * @uxon-type string
     * 
     * @param string $string
     * @return VariableToColumnMapping
     */
    protected function setFrom(string $string) : VariableToColumnMapping
    {
        return $this->setVariable($string);
    }

    /**
     * 
     * @return \exface\Core\Interfaces\Model\ExpressionInterface
     */
    public function getToExpression() : ExpressionInterface
    {
        return $this->toExpression;
    }

    /**
     * 
     * @param ExpressionInterface $expression
     * @throws DataMappingConfigurationError
     * @return VariableToColumnMapping
     */
    public function setToExpression(ExpressionInterface $expression) : VariableToColumnMapping
    {
        if ($expression->isReference()){
            throw new DataMappingConfigurationError($this, 'Cannot use widget links as expressions in data mappers!');
        }
        $this->toExpression = $expression;
        return $this;
    }
    
    /**
     * This is the expression, that the from-expression is going to be translated to.
     * 
     * @uxon-property to
     * @uxon-type metamodel:expression
     * @uxon-required true
     * 
     * @param string $string
     * @return VariableToColumnMapping
     */
    public function setTo(string $string) : VariableToColumnMapping
    {
        $this->setToExpression(ExpressionFactory::createFromString($this->getWorkbench(), $string, $this->getMapper()->getToMetaObject()));
        return $this;
    }
    
    /**
     * Set this to true if the variable probably contains an arry of values and
     * you want every row in the data sheet to be copied and filled with values of tha variable once for each copy.
     * 
     * Meaning if you have 4 rows in your data sheet and 2 values in your saved variable the result will have 8 rows,
     * 4 rows for each value in the variable.
     *
     * @uxon-property duplicate_rows
     * @uxon-type bool
     * @uxon-default false
     *
     * @param string $string
     * @return VariableToColumnMapping
     */
    public function setDuplicateRows(bool $trueOrFalse) : VariableToColumnMapping
    {
        $this->duplicateRows = $trueOrFalse;
        return $this;
    }
    
    /**
     * Set to FALSE to prevent variables from adding rows to empty data sheets.
     *
     * A variable mapping applied to an empty to-sheet will normally add as many rows to an empty
     * data sheet as values are saved in the variable add a new row with the generated value.
     * This option can explicitly disable this behavior for a single mapping.
     * There is also a similar global setting `inherit_empty_data` for the entire mapper.
     *
     * @uxon-property create_row_in_empty_data
     * @uxon-type bool
     * @uxon-default true
     *
     * @param bool $value
     * @return VariableToColumnMapping
     */
    public function setCreateRowInEmptyData(bool $value) : VariableToColumnMapping
    {
        $this->createRowInEmptyData = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $varName = $this->getVariableName();
        $varVal = $this->readVariable($varName);
        $toExpr = $this->getToExpression();
        
        $log = "Variable `{$varName}` -> column `{$toExpr->__toString()}`";        
        
        if (! $newCol = $toSheet->getColumns()->getByExpression($toExpr)) {
            $newCol = $toSheet->getColumns()->addFromExpression($toExpr);
        }
        switch (true) {
            // Arrays
            case is_array($varVal):
                if ($toSheet->isEmpty() === true && $this->createRowInEmptyData === false) {
                    $log .= " Will not add row to empty data because `create_row_in_empty_data` is `false`.";
                    break;                    
                }
                if ($toSheet->isEmpty() === true && $this->createRowInEmptyData === true) {
                    $log .= " Adding new rows because the to-sheet was empty.";
                    foreach ($varVal as $rowVal) {
                        $toSheet->addRow([$newCol->getName() => $rowVal]);
                    }                
                } elseif ($this->duplicateRows === true) {
                    $toSheetNew = $toSheet->copy();
                    $toSheetNew->removeRows();
                    $log .= " Duplicating rows because because `duplicate_rows` is `true` and values saved in variable is an array.";
                    foreach ($varVal as $val) {
                        $toSheetCopy = $toSheet->copy();
                        $toSheetCopy->getColumns()->getByExpression($toExpr)->setValueOnAllRows($val);
                        $toSheetNew->addRows($toSheetCopy->getRows());
                    }
                    $toSheet->removeRows()->addRows($toSheetNew->getRows());
                } elseif ($toSheet->countRows() === count($varVal)) {
                    $log .= " Row counts matches values count. Setting values for rows.";
                    $newCol->setValues($varVal);
                } else {
                    throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot map array variable "' . $varName . '" with ' . count($varVal) . ' values to column "' . $toExpr->__toString() . '" with ' . $toSheet->countRows() . ' rows!');
                }
                break;
            // Scalars
            default:
                $newCol->setValueOnAllRows($varVal);
                if ($toSheet->isEmpty() === true && $this->createRowInEmptyData === true) {
                    $log .= " Adding new row because the to-sheet was empty.";
                    $toSheet->addRow([$newCol->getName() => $varVal]);
                }
        }
        if ($logbook !== null) $logbook->addLine($log);
        
        return $toSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        return [];
    }
    
    /**
     * 
     * @param string $name
     * @return mixed
     */
    protected function readVariable(string $name)
    {
        return $this->getWorkbench()->getContext()->getScopeRequest()->getVariable($name);
    }
}