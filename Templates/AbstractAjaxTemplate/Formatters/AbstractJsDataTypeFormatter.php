<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface;
use exface\Core\Interfaces\ExfaceClassInterface;

abstract class AbstractJsDataTypeFormatter implements JsDataTypeFormatterInterface, ExfaceClassInterface
{
    private $dataType = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see JsDataTypeFormatterInterface::__construct()
     */
    public function __construct(DataTypeInterface $dataType)
    {
        $this->setDataType($dataType);
    }
    
    /**
     * Sets the data type for this formatter. 
     * 
     * Override this method to include additional checks for specific compatible data types.
     * 
     * @param DataTypeInterface $dataType
     * @return \exface\Core\Templates\AbstractAjaxTemplate\Formatters\AbstractJsDataTypeFormatter
     */
    protected function setDataType(DataTypeInterface $dataType)
    {
        $this->dataType = $dataType;
        return $this;
    }
    
    /**
     * Returns the data type used in this formatter.
     * 
     * @return \exface\Core\Interfaces\DataTypes\DataTypeInterface
     */
    public function getDataType()
    {
        return $this->dataType;
    }
    
    public function getWorkbench()
    {
        return $this->getDataType()->getWorkbench();
    }
}
