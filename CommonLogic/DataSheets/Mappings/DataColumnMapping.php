<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\DataSheets\DataColumnMappingInterface;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Maps one data sheet column to another column of another sheet.
 * 
 * @see DataColumnMappingInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataColumnMapping extends AbstractDataSheetMapping implements DataColumnMappingInterface
{
    private $fromExpression = null;
    
    private $toExpression = null;
    
    private $createRowInEmptyData = true;

    private $ignoreIfMissingFromColumn = false;
    
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
     * Any use of this expression in the data sheet will be transformed to the to-expression in the mapped sheet.
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
    public function getToExpression()
    {
        return $this->toExpression;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnMappingInterface::setToExpression()
     */
    public function setToExpression(ExpressionInterface $expression)
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::setTo()
     */
    public function setTo($string)
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
        $fromExpr = $this->getFromExpression();
        $toExpr = $this->getToExpression();
        
        $log = "Column `{$fromExpr->__toString()}` -> `{$toExpr->__toString()}`.";
        
        switch (true) {
            // Constants and static formulas
            case $fromExpr->isStatic():
                $newCol = $toSheet->getColumns()->addFromExpression($toExpr)->setValuesByExpression($fromExpr);
                // If the sheet has no rows, setValuesByExpression() will not have an effect, so
                // we need to add a row manually.
                if ($toSheet->isEmpty() === true) {
                    if ($this->getCreateRowInEmptyData() === true) {
                        $log .= ' Adding a new row because the to-sheet was empty.';
                        $toSheet->addRow([$newCol->getName() => $fromExpr->evaluate()]);
                    } else {
                        $log .= ' Will not add row to empty data because `create_row_in_empty_data` is `false`.';
                    }
                }
                break;
            // Formulas with data
            case $fromExpr->isFormula():
                $newCol = $toSheet->getColumns()->addFromExpression($toExpr);
                $newCol->setValues($fromExpr->evaluate($fromSheet));
                // If the sheet has no rows, setValuesByExpression() will not have an effect, so
                // we need to add a row manually. But this will only work if the from-sheet
                // has at least one row - othewise non-static formulas will throw an error!
                if ($toSheet->isEmpty() === true) {
                    if ($fromSheet->isEmpty() === false && $this->getCreateRowInEmptyData() === true) {
                        $log .= ' Adding a new row because the to-sheet was empty.';
                        $toSheet->addRow([$newCol->getName() => $fromExpr->evaluate($fromSheet, 0)]);
                    } else {
                        $log .= ' Will not add row to empty data because ' . ($fromSheet->isEmpty() ? 'from-sheet is empty.' : '`create_row_in_empty_data` is `false`.');
                    }
                }
                break;
            // Data column references
            case $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr):
                $toSheet->getColumns()->addFromExpression($toExpr, null, $fromCol->getHidden())->setValues($fromCol->getValues(false));
                break;
            // Data column references should not result in errors if the data sheet is completely empty
            // Otherwise input-mappers would always produce errors on empty input data!
            case $fromSheet->getColumns()->isEmpty() && ! $fromExpr->isReference():
                if ($logbook !== null) $logbook->addLine($log . ' Not required because from-sheet is empty.');
                return $toSheet;
            // If not enough data, but explicitly configured to ignore it, exit here
            case $this->getIgnoreIfMissingFromColumn() === true && ($fromExpr->isMetaAttribute() || $fromExpr->isFormula() || $fromExpr->isUnknownType()):
                if ($logbook !== null) $logbook->addLine($log . ' Ignored because `ignore_if_missing_from_column` is `true` and not from-data was found.');
                return $toSheet;
            default:
                if ($fromExpr->isMetaAttribute()) {
                    throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot map from attribute "' . $fromExpr->toString() . '" in a column-to-column mapping: there is no matching column in the from-data and it cannot be loaded automatically (e.g. because the from-object ' . $fromSheet->getMetaObject() .' has no UID attribute)!', '7H6M243');
                }
                throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot use "' . $fromExpr->toString() . '" as from-expression in a column-to-column mapping: only data column names, constants and formulas allowed!', '7H6M243');
        }
        
        if ($logbook !== null) $logbook->addLine($log);
        
        return $toSheet;
    }
    
    /**
     *
     * @return bool
     */
    public function getCreateRowInEmptyData() : bool
    {
        return $this->createRowInEmptyData;
    }
    
    /**
     * Set to FALSE to prevent static expressions and formulas from adding rows to empty data sheets.
     * 
     * A static from-expression like `=Today()` applied to an empty to-sheet will normally
     * add a new row with the generated value. This option can explicitly disable this behavior
     * for a single mapping. There is also a similar global setting `inherit_empty_data` for
     * the entire mapper. 
     *
     * @uxon-property create_row_in_empty_data
     * @uxon-type bool
     *
     * @param bool $value
     * @return DataColumnMapping
     */
    public function setCreateRowInEmptyData(bool $value) : DataColumnMapping
    {
        $this->createRowInEmptyData = $value;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    protected function getIgnoreIfMissingFromColumn() : bool
    {
        return $this->ignoreIfMissingFromColumn;
    }

    /**
     * Set to TRUE if this mapping is only to be applied if there is a corresponding from-data
     * 
     * By default the mapping will result in an error if the from-data does not have the 
     * required data.
     * 
     * @uxon-property ignore_if_missing_from_column
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return DataColumnMapping
     */
    protected function setIgnoreIfMissingFromColumn(bool $trueOrFalse) : DataColumnMapping
    {
        $this->ignoreIfMissingFromColumn = $trueOrFalse;
        return $this;
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
}