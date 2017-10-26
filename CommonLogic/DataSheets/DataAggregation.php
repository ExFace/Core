<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\CommonLogic\Workbench;

class DataAggregation implements iCanBeConvertedToUxon
{

    const AGGREGATION_SEPARATOR = ':';

    private $attribute_alias = null;

    private $data_sheet = null;

    function __construct(DataSheetInterface $data_sheet)
    {
        $this->data_sheet = $data_sheet;
    }

    public function getAttributeAlias()
    {
        return $this->attribute_alias;
    }

    public function setAttributeAlias($value)
    {
        $this->attribute_alias = $value;
        return $this;
    }

    public function getDataSheet()
    {
        return $this->data_sheet;
    }

    public function setDataSheet(DataSheetInterface $data_sheet)
    {
        $this->data_sheet = $data_sheet;
        return $this;
    }

    public function exportUxonObject()
    {
        $uxon = $this->getDataSheet()->getWorkbench()->createUxonObject();
        $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
        return $uxon;
    }

    public function importUxonObject(UxonObject $uxon)
    {
        $this->setAttributeAlias($uxon->getProperty('attribute_alias'));
    }

    /**
     * PRODUCT->SIZE:CONCAT(',') --> CONCAT(',')
     *
     * @param string $attribute_alias            
     * @return AggregatorInterface|boolean
     */
    public static function getAggregatorFromAlias(Workbench $workbench, $attribute_alias)
    {
        $aggregator_pos = strpos($attribute_alias, self::AGGREGATION_SEPARATOR);
        if ($aggregator_pos !== false) {
            return new Aggregator($workbench, substr($attribute_alias, $aggregator_pos + 1));
        } else {
            return false;
        }
    }
    
    public static function stripAggregator($attribute_alias)
    {
        $aggregator_pos = strpos($attribute_alias, self::AGGREGATION_SEPARATOR);
        if ($aggregator_pos !== false){
            return substr($attribute_alias, 0, $aggregator_pos);
        } else {
            return $attribute_alias;
        }
    }

    /**
     * Returns a copy of this sorter still belonging to the same data sheet
     *
     * @return DataSorter
     */
    public function copy()
    {
        return clone $this;
    }
}