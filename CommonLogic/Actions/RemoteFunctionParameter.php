<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iCallRemoteFunction;

class RemoteFunctionParameter implements iCanBeConvertedToUxon, WorkbenchDependantInterface
{
    use ImportUxonObjectTrait;
    
    private $name = null;
    
    private $required = false;
    
    private $dataType = null;
    
    private $dataTypeUxon = null;
    
    private $action = null;
    
    public function __construct(ActionInterface $action, UxonObject $uxon)
    {
        $this->action = $action;
        $this->importUxonObject($uxon);
    }
    
    public function exportUxonObject()
    {
        return new UxonObject();
    } 
    
    /**
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }
    
    /**
     * 
     * @param string $value
     * @return RemoteFunctionParameter
     */
    public function setName(string $value) : Odata2FunctionImportParameter
    {
        $this->name = $value;
        return $this;
    }
    
    /**
     *
     * @return DataTypeInterface
     */
    public function getDataType() : DataTypeInterface
    {
        if ($this->dataType === null) {
            $this->dataType = DataTypeFactory::createFromUxon($workbench, $this->dataTypeUxon);
        }
        return $this->dataType;
    }
    
    /**
     *
     * @param UxonObject $value
     * @return RemoteFunctionParameter
     */
    public function setDataType(UxonObject $uxon) : Odata2FunctionImportParameter
    {
        $this->dataType = null;
        $this->dataTypeUxon = $uxon;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isRequired() : bool
    {
        return $this->required;
    }
    
    /**
     * 
     * @param bool $value
     * @return RemoteFunctionParameter
     */
    public function setRequired(bool $value) : Odata2FunctionImportParameter
    {
        $this->required = $value;
        return $this;
    }
    
    public function getAction() : iCallRemoteFunction
    {
        return $this->action;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->action->getWorkbench();
    }
  
}