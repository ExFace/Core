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
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

class ServiceParameter implements ServiceParameterInterface
{
    use ImportUxonObjectTrait;
    
    private $name = null;
    
    private $required = false;
    
    private $defaultValue = null;
    
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
        $uxon = new UxonObject([
            'name' => $this->getName(),
            'required' => $this->isRequired()
        ]);
        
        return $uxon;
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
     * The technical name of the parameter (i.e. variable name)
     * 
     * @uxon-property name
     * @uxon-type string
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
            $this->dataType = DataTypeFactory::createFromUxon($this->getWorkbench(), $this->dataTypeUxon);
        }
        return $this->dataType;
    }
    
    /**
     * A UXON-description of a data type.
     * 
     * If not set, a simple string data type will be assumed.
     * 
     * @uxon-property data_type
     * @uxon-type \exface\Core\CommonLogic\DataTypes\AbstractDataType
     * @uxon-template {"alias": ""}
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
     * Set to TRUE to mark the parameter as mandatory.
     * 
     * @uxon-property required
     * @uxon-type boolean
     * @uxon-default false
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ServiceParameterInterface::isValidValue()
     */
    public function isValidValue($val): bool
    {
        try {
            $this->parseValue($val);
        } catch (DataTypeValidationError $e) {
            return false;
        }
        return true;
    }
    
    public function parseValue($val) : string
    {
        if ($this->isRequired() && $this->getDataType()->isEmptyValue($val)) {
            throw new ActionInputMissingError($this->getAction(), 'Service parameter "' . $this->getName() . '" cannot be empty!');
        }
        
        return $this->getDataType()->parse($val);
    }
    
    public function getDefaultValue() : string
    {
        return $this->defaultValue;
    }
    
    public function hasDefaultValue() : bool
    {
        return $this->defaultValue !== null;
    }
    
    /**
     * Use this value if no input data available for this parameter
     * 
     * @uxon-property default_value
     * @uxon-type string
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ServiceParameterInterface::setDefaultValue()
     */
    public function setDefaultValue(string $string) : ServiceParameterInterface
    {
        $this->defaultValue = $string;
        return $this;
    }
}