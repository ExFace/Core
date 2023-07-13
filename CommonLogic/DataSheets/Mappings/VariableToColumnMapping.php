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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $varName = $this->getVariableName();
        $varVal = $this->readVariable($varName);
        $toExpr = $this->getToExpression();
        
        if ($logbook !== null) $logbook->addLine("Variable `{$varName}` -> column `{$toExpr->__toString()}`");
        
        if (! $newCol = $toSheet->getColumns()->getByExpression($toExpr)) {
            $newCol = $toSheet->getColumns()->addFromExpression($toExpr);
        }
        switch (true) {
            // Arrays
            case is_array($varVal):
                if ($toSheet->isEmpty() === true) {
                    foreach ($varVal as $rowVal) {
                        $toSheet->addRow([$newCol->getName() => $rowVal]);
                    } 
                } elseif ($toSheet->countRows() === count($varVal)) {
                    $newCol->setValues($varVal);
                } else {
                    throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot map array variable "' . $varName . '" with ' . count($varVal) . ' values to column "' . $toExpr->__toString() . '" with ' . $toSheet->countRows() . ' rows!');
                }
                break;
            // Scalars
            default:
                $newCol->setValueOnAllRows($varVal);
                if ($toSheet->isEmpty() === true) {
                    $toSheet->addRow([$newCol->getName() => $varVal]);
                }
        }
        
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
    
    protected function readVariable(string $name)
    {
        return $this->getWorkbench()->getContext()->getScopeRequest()->setVariable($name);
    }
}