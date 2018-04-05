<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

abstract class AbstractJsDataTypeFormatter implements JsDataTypeFormatterInterface, WorkbenchDependantInterface
{
    /**
     * 
     * @var DataTypeInterface
     */
    private $dataType = null;
    
    /**
     * 
     * @param DataTypeInterface $dataType
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::getDataType()
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
