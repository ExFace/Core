<?php
namespace exface\Core\Widgets\Parts\Pivot;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *        
 */
class PivotValue extends PivotDimension
{
    private $aggregator = null;    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::getUxonObject();
        $uxon->setProperty($this->getAggregator());
        
        return $uxon;
    }
    
    /**
     * 
     * @return string
     */
    public function getAggregator() : string
    {
        return $this->aggregator;
    }
    
    /**
     * 
     * @param string $value
     * @return PivotValue
     */
    protected function setAggregator(string $value) : PivotValue
    {
        $this->aggregator = $value;
        return $this;
    }    
}