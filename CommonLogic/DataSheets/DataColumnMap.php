<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\Model\Expression;

class DataColumnMap implements iCanBeConvertedToUxon {
    
    use ImportUxonObjectTrait;
    
    private $fromExpression = null;
    
    private $toExpression = null;
    
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
     * @return DataColumnMap
     * @return Expression
     */
    public function getToExpression()
    {
        return $this->toExpression;
    }

    /**
     * 
     * @param string|Expression $stringOrExpression
     * @return DataColumnMap
     */
    public function setFromExpression($stringOrExpression)
    {
        $this->fromExpression = $stringOrExpression;
        return $this;
    }

    /**
     * 
     * @param string|Expression $stringOrExpression
     * @return DataColumnMap
     */
    public function setToExpression($stringOrExpression)
    {
        $this->toExpression = $stringOrExpression;
        return $this;
    }
   
    
     
    
}