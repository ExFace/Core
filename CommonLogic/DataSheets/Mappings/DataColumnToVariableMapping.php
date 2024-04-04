<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Factories\ExpressionFactory;

/**
 * Stores values from a data sheet column into a context variable.
 * 
 * The variable will be available in the request context scope for the lifetime of the current request.
 * It can be read via `variable_to_column_mapping` or `=GetContextVar()` formula.
 * 
 * The variable will be an array, if the data being read has multiple rows, while single-row data
 * or static values will be stored as scalars (strings, numbers, etc.)
 * 
 * **NOTE:** this mapping does not change anything in the to-sheet!
 * 
 * @author Andrej Kabachnik
 *
 */
class DataColumnToVariableMapping extends AbstractDataSheetMapping
{
    private $fromExpression = null;
    
    private $varName = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnMappingInterface::getFromExpression()
     */
    public function getFromExpression()
    {
        return $this->fromExpression;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnMappingInterface::setFromExpression()
     */
    public function setFromExpression(ExpressionInterface $expression)
    {
        if ($expression->isReference()){
            throw new DataMappingConfigurationError($this, 'Cannot use widget links as expressions in data mappers!');
        }
        $this->fromExpression = $expression;
        return $this;
    }
    
    /**
     * Any use of this expression in the data sheet will be stored in the variable.
     *
     * The expression can be an attribute alias, a constant or a formula.
     *
     * @uxon-property from
     * @uxon-type metamodel:expression
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::setFrom()
     */
    public function setFrom($string)
    {
        $this->setFromExpression(ExpressionFactory::createFromString($this->getWorkbench(), $string, $this->getMapper()->getFromMetaObject()));
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnMappingInterface::getToExpression()
     */
    public function getVariableName()
    {
        return $this->varName;
    }
    
    /**
     * This is the expression, that the from-expression is going to be translated to.
     *
     * @uxon-property variable
     * @uxon-type string
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::setTo()
     */
    public function setVariable($string)
    {
        $this->varName = $string;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $fromExpr = $this->getFromExpression();
        $varName = $this->getVariableName();
        
        if ($logbook !== null) $logbook->addLine("Column `{$fromExpr->__toString()}` -> variable `{$varName}`");
        
        switch (true) {
            // Constants and static formulas
            case $fromExpr->isStatic():
                $var = $fromExpr->evaluate();
                break;
            // Formulas with data
            case $fromExpr->isFormula():
                $var = $fromExpr->evaluate($fromSheet);
                break;
            // Data column references
            case $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr):
                $var = $fromCol->getValues(false);
                break;
            // Data column references should not result in errors if the data sheet is completely empty
            // Otherwise input-mappers would always produce errors on empty input data!
            case $fromSheet->getColumns()->isEmpty() && ! $fromExpr->isReference():
                $var = null;
                break;
            default:
                if ($fromExpr->isMetaAttribute()) {
                    throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot map from attribute "' . $fromExpr->toString() . '" in a column-to-column mapping: there is no matching column in the from-data and it cannot be loaded automatically (e.g. because the from-object ' . $fromSheet->getMetaObject() .' has no UID attribute)!', '7H6M243');
                }
                throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot use "' . $fromExpr->toString() . '" as from-expression in a column-to-column mapping: only data column names, constants and formulas allowed!', '7H6M243');
        }
        
        if (is_array($var) && count($var) === 1) {
            $var = reset($var);
        }
        
        $this->storeVariable($varName, $var);
        
        return $toSheet;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        return [$this->getFromExpression()];
    }
    
    /**
     * 
     * @param string $name
     * @param mixed $value
     */
    protected function storeVariable(string $name, $value)
    {
        $this->getWorkbench()->getContext()->getScopeRequest()->setVariable($name, $value);
    }
}