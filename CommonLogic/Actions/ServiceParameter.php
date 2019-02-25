<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iCallService;
use exface\Core\Interfaces\Actions\ServiceParameterInterface;
use exface\Core\Exceptions\Actions\ActionInputMissingError;

class ServiceParameter implements ServiceParameterInterface
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
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
     * @return ServiceParameter
     */
    public function setName(string $value) : ServiceParameter
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
     * @return ServiceParameter
     */
    public function setDataType(UxonObject $uxon) : ServiceParameter
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
     * @return ServiceParameter
     */
    public function setRequired(bool $value) : ServiceParameter
    {
        $this->required = $value;
        return $this;
    }
    
    public function getAction() : iCallService
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
    
    public function sanitize($val) : bool
    {
        if ($this->isRequired() && $this->getDataType()->isEmptyValue($val)) {
            throw new ActionInputMissingError($this->getAction(), 'Service parameter "' . $this->getName() . '" cannot be empty!');
        }
    }
  
}