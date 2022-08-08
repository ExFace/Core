<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\DataSheets\DataSheetMapperError;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\DataSheets\DataColumnMappingInterface;

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
    
    private $createRowInEmptyData = false;
    
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
            throw new DataSheetMapperError($this->getMapper(), 'Cannot use widget links as expressions in data mappers!');
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
            throw new DataSheetMapperError($this->getMapper(), 'Cannot use widget links as expressions in data mappers!');
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
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet)
    {
        $fromExpr = $this->getFromExpression();
        $toExpr = $this->getToExpression();
        
        switch (true) {
            // Constants and static formulas
            case $fromExpr->isStatic():
                $newCol = $toSheet->getColumns()->addFromExpression($toExpr)->setValuesByExpression($fromExpr);
                // If the sheet has no rows, setValuesByExpression() will not have an effect, so
                // we need to add a row manually.
                if ($toSheet->isEmpty() === true) {
                    $toSheet->addRow([$newCol->getName() => $fromExpr->evaluate()]);
                }
                break;
            // Formulas with data
            case $fromExpr->isFormula():
                $newCol = $toSheet->getColumns()->addFromExpression($toExpr);
                $newCol->setValues($fromExpr->evaluate($fromSheet));
                // If the sheet has no rows, setValuesByExpression() will not have an effect, so
                // we need to add a row manually.
                if ($toSheet->isEmpty() === true) {
                    $toSheet->addRow([$newCol->getName() => $fromExpr->evaluate($fromSheet, 0)]);
                }
                break;
            // Data column references
            case $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr):
                $toSheet->getColumns()->addFromExpression($toExpr, '', $fromCol->getHidden())->setValues($fromCol->getValues(false));
                break;
            // Data column references should not result in errors if the data sheet is completely empty
            // Otherwise input-mappers would always produce errors on empty input data!
            case $fromSheet->getColumns()->isEmpty() && ! $fromExpr->isReference():
                return $toSheet;
            default:
                if ($fromExpr->isMetaAttribute()) {
                    throw new DataSheetMapperError($this->getMapper(), 'Cannot map from attribute "' . $fromExpr->toString() . '" in a column-to-column mapping: there is no matching column in the from-data and it cannot be loaded automatically (e.g. because the from-object ' . $fromSheet->getMetaObject() .' has no UID attribute)!', '7H6M243');
                }
                throw new DataSheetMapperError($this->getMapper(), 'Cannot use "' . $fromExpr->toString() . '" as from-expression in a column-to-column mapping: only data column names, constants and formulas allowed!', '7H6M243');
        }
        
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
     * Set to TRUE to make this mapper add a row with it's column to empty input sheets.
     * 
     * By default, a column mapper will not have effect on empty data sheets. This property
     * can be used to make it add a new row if a static value is used in the mapper. This
     * allows to create update-by-filter actions with static values using input mappers.
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        return [$this->getFromExpression()];
    }
}