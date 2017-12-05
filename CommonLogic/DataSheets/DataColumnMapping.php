<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\DataSheets\DataSheetMapperError;
use exface\Core\Interfaces\DataSheets\DataColumnMappingInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;

/**
 * Maps one data sheet column to another column of another sheet.
 * 
 * @see DataColumnMappingInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataColumnMapping implements DataColumnMappingInterface {
    
    use ImportUxonObjectTrait;
    
    private $mapper = null;
    
    private $fromExpression = null;
    
    private $toExpression = null;
    
    public function __construct(DataSheetMapper $mapper)
    {
        $this->mapper = $mapper;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        // TODO
        return $uxon;
    }
    
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
     * @uxon-type string
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
     * @uxon-property from
     * @uxon-type string
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
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getMapper()
     */
    public function getMapper()
    {
        return $this->mapper;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getMapper()->getWorkbench();
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
        
        if ($fromExpr->isConstant()){
            $toSheet->getColumns()->addFromExpression($toExpr)->setValuesByExpression($fromExpr);
        } elseif ($fromCol = $fromSheet->getColumns()->getByExpression($fromExpr)){
            $toSheet->getColumns()->addFromExpression($toExpr, '', $fromCol->getHidden())->setValues($fromCol->getValues(false));
        }
        
        return $toSheet;
    }
    
}