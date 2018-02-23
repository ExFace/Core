<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Exceptions\DomainException;
use exface\Core\Exceptions\DataSheets\DataSheetStructureError;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\Interfaces\Model\AggregatorInterface;

class DataColumnTotal implements iCanBeConvertedToUxon, ExfaceClassInterface
{

    private $function = null;

    private $data_column = null;

    function __construct(DataColumnInterface $column, $aggregator_string = null)
    {
        $this->setColumn($column);
        if (! is_null($aggregator_string)) {
            $this->setAggregator(new Aggregator($this->getWorkbench(), $aggregator_string));
        }
    }

    /**
     *
     * @return DataColumn
     */
    public function getColumn()
    {
        return $this->data_column;
    }

    public function setColumn(DataColumnInterface $column_instance)
    {
        if (! $column_instance->getAttribute()) {
            throw new DataSheetStructureError($column_instance->getDataSheet(), 'Cannot add a total to column "' . $column_instance->getName() . '": this column does not represent a meta attribute!', '6UQBUVZ');
        }
        $this->data_column = $column_instance;
        return $this;
    }

    /**
     * 
     * @return AggregatorInterface
     */
    public function getAggregator()
    {
        return $this->function;
    }

    /**
     * 
     * @param AggregatorInterface|string $aggregator
     * @return \exface\Core\CommonLogic\DataSheets\DataColumnTotal
     */
    public function setAggregator($aggregator_or_string)
    {
        if ($aggregator_or_string instanceof AggregatorInterface){
            $aggregator = $aggregator_or_string;
        } else {
            $aggregator = new Aggregator($this->getWorkbench(), $aggregator_or_string);
        }
        $this->function = $aggregator;
        return $this;
    }

    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->setProperty('function', $this->getAggregator()->exportString());
        return $uxon;
    }

    public function importUxonObject(UxonObject $uxon)
    {
        // Map "function" to the aggregator for backwards compatibility! (13.09.2017)
        if ($uxon->hasProperty('function')){
            $this->setAggregator($uxon->getProperty('function'));
        }
        
        if ($uxon->hasProperty('aggregator')){
            $this->setAggregator($uxon->getProperty('aggregator'));
        }
    }

    public function getWorkbench()
    {
        return $this->getColumn()->getWorkbench();
    }
}