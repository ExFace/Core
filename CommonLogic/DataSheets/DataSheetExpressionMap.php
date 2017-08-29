<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

class DataSheetExpressionMap implements iCanBeConvertedToUxon, ExfaceClassInterface {
    
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
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
     * @return Expression
     */
    public function getFromExpression()
    {
        return $this->fromExpression;
    }

    /**
     * 
     * @param Expression $stringOrExpression
     * @return DataSheetExpressionMap
     */
    public function setFromExpression($stringOrExpression)
    {
        $this->fromExpression = $stringOrExpression;
        return $this;
    }
    
    /**
     * 
     * @param string $string
     */
    public function setFrom($string)
    {
        $this->setFromExpression(ExpressionFactory::createFromString($this->getWorkbench(), $string, $this->getMapper()->getFromMetaObject()));
        return $this;
    }

    /**
     * 
     * @return DataSheetExpressionMap
     * @return Expression
     */
    public function getToExpression()
    {
        return $this->toExpression;
    }

    /**
     * 
     * @param Expression $stringOrExpression
     * @return DataSheetExpressionMap
     */
    public function setToExpression($stringOrExpression)
    {
        $this->toExpression = $stringOrExpression;
        return $this;
    }
    
    /**
     *
     * @param string $string
     */
    public function setTo($string)
    {
        $this->setToExpression(ExpressionFactory::createFromString($this->getWorkbench(), $string, $this->getMapper()->getToMetaObject()));
        return $this;
    }
    
   
    /**
     * 
     * @return \exface\Core\CommonLogic\DataSheets\DataSheetMapper
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
     * @param DataSheetInterface $fromSheet
     * @param DataSheetInterface $toSheet
     * @return $this;
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet)
    {
        if ($fromCol = $fromSheet->getColumns()->getByExpression($this->getFromExpression())){
            $toSheet->getColumns()->addFromExpression($this->getToExpression(), '', $fromCol->getHidden())->setValues($fromCol->getValues(false));
        }
        
        // TODO map filters, sorters and aggregators
        
        return $toSheet;
    }
    
}