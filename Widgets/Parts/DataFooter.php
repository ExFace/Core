<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;

/**
 * A footer for DataColumn widgets with a built-in option to show aggregations (totals).
 * 
 * @author Andrej Kabachnik
 *
 */
class DataFooter implements WidgetPartInterface
{
    use ImportUxonObjectTrait;
    
    private $aggregator = null;
    
    private $columnWidget = null;
    
    public function __construct(DataColumn $columnWidget, UxonObject $uxon = null)
    {
        $this->columnWidget = $columnWidget;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->columnWidget->getMetaObject();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->columnWidget;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getWidget()->getWorkbench();
    }
    
    public function exportUxonObject()
    {
        $uxon = new UxonObject([]);
        
        if ($this->hasAggregator() === true) {
            $uxon->setProperty('aggregator', $this->getAggregator()->__toString);
        }
        
        return $uxon;
    }
    
    /**
     *
     * @return AggregatorInterface
     */
    public function getAggregator() : AggregatorInterface
    {
        if (! $this->aggregator instanceof AggregatorInterface) {
            $this->aggregator = new Aggregator($this->getWorkbench(), $this->aggregator);
        }
        return $this->aggregator;
    }
    
    /**
     * 
     * @uxon-property aggregator
     * @uxon-type metamodel:aggregator
     * 
     * @param string|AggregatorInterface $value
     * @return DataFooter
     */
    public function setAggregator($value) : DataFooter
    {
        $this->aggregator = $value;
        return $this;
    }
    
    public function hasAggregator() : bool
    {
        return $this->aggregator !== null;
    }
}