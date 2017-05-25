<?php

namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Exceptions\DomainException;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;
use exface\Core\Exceptions\DataSheets\DataSheetStructureError;

class DataColumnTotal implements iCanBeConvertedToUxon, ExfaceClassInterface
{

    private $function = null;

    private $data_column = null;

    function __construct(DataColumnInterface $column, $function_name = null)
    {
        $this->setColumn($column);
        if (! is_null($function_name)) {
            $this->setFunction($function_name);
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

    public function getFunction()
    {
        return $this->function;
    }

    public function setFunction($value)
    {
        if (! defined('EXF_AGGREGATOR_' . $value)) {
            throw new DomainException('Cannot set totals function "' . $value . '" for data column "' . $this->getColumn()->getName() . '": invalid function!', '6T5UXLD');
        }
        $this->function = $value;
        return $this;
    }

    public function exportUxonObject()
    {
        $uxon = $this->getColumn()
            ->getDataSheet()
            ->getWorkbench()
            ->createUxonObject();
        $uxon->setProperty('function', $this->getFunction());
        return $uxon;
    }

    public function importUxonObject(UxonObject $uxon)
    {
        $this->setFunction($uxon->getProperty('function'));
    }

    public function getWorkbench()
    {
        return $this->getColumn()->getWorkbench();
    }
}