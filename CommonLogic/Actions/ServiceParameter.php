<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\ServiceParameterInterface;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Interfaces\WorkbenchDependantInterface;

class ServiceParameter implements ServiceParameterInterface
{
    use ImportUxonObjectTrait;
    
    private $name = null;
    
    private $group = null;
    
    private $description = '';
    
    private $required = false;
    
    private $empty = false;
    
    private $defaultValue = null;
    
    private $dataType = null;
    
    private $dataTypeUxon = null;
    
    private $service = null;
    
    private $dataSourceProperties = null;
    
    public function __construct(WorkbenchDependantInterface $service, UxonObject $uxon = null)
    {
        $this->service = $service;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
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
            'data_type' => $this->getDataType()->exportUxonObject()
        ]);

        if (null !== $val = $this->getDescription()) {
            $uxon->setProperty('description', $val);
        }
        if (null !== $val = $this->getGroup()) {
            $uxon->setProperty('group', $val);
        }
        if (true === $val = $this->isRequired()) {
            $uxon->setProperty('required', $val);
        }
        if (null !== $val = $this->getDefaultValue()) {
            $uxon->setProperty('default_value', $val);
        }
        if (! ($val = $this->getCustomProperties())->isEmpty()) {
            $uxon->setProperty('custom_properties', $val);
        }
        
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
            if ($this->dataTypeUxon !== null) {
                $this->dataType = DataTypeFactory::createFromUxon($this->getWorkbench(), $this->dataTypeUxon);
            } else {
                $this->dataType = DataTypeFactory::createBaseDataType($this->getWorkbench());
            }
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
    
    /**
     *
     * @return bool
     */
    public function isEmpty() : bool
    {
        return $this->empty;
    }
    
    /**
     * Set to TRUE to mark the parameter as empty.
     *
     * @uxon-property empty
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return ServiceParameter
     */
    public function setEmpty(bool $value) : ServiceParameter
    {
        $this->empty = $value;
        return $this;
    }
    
    protected function getService() : WorkbenchDependantInterface
    {
        return $this->service;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->service->getWorkbench();
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
        $service = $this->getService();
        if ($this->isRequired() && $this->getDataType()->isValueEmpty($val)) {
            switch (true) {
                case $service instanceof ActionInterface:
                    throw new ActionInputMissingError($service, 'Service parameter "' . $this->getName() . '" cannot be empty!');
                default:
                    throw new UnexpectedValueException('Service parameter "' . $this->getName() . '" cannot be empty!');
            }
        }
        
        return $this->getDataType()->parse($val);
    }
    
    public function getDefaultValue()
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
    public function setDefaultValue($string) : ServiceParameterInterface
    {
        $this->defaultValue = $string;
        return $this;
    }
    
    /**
     * 
     * @return UxonObject
     */
    public function getCustomProperties() : UxonObject
    {
        if ($this->dataSourceProperties === null) {
            $this->dataSourceProperties = new UxonObject();
        }
        return $this->dataSourceProperties;
    }
    
    /**
     * Custom parameter properties (similar to data address settings in attributes).
     * 
     * @uxon-property custom_properties
     * @uxon-type object
     * @uxon-template {"": ""}
     * 
     * @param UxonObject $value
     * @return ServiceParameter
     */
    public function setCustomProperties(UxonObject $value) : ServiceParameterInterface
    {
        $this->dataSourceProperties = $value;
        return $this;
    }
    
    /**
     * 
     * @param string $name
     * @return string|NULL
     */
    public function getCustomProperty(string $name) : ?string
    {
        return $this->getCustomProperties()->getProperty($name);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ServiceParameterInterface::getDescription()
     */
    public function getDescription() : string
    {
        return $this->description;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ServiceParameterInterface::setDescription()
     */
    public function setDescription(string $value) : ServiceParameterInterface
    {
        $this->description = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ServiceParameterInterface::getGroup()
     */
    public function getGroup(string $default = null) : ?string
    {
        return $this->group ?? $default;
    }
    
    /**
     * The group of the perameter in case tha service takes different parameter groups (e.g. CLI arguments and options)
     * 
     * @uxon-property group
     * @uxon-type string
     * 
     * @see \exface\Core\Interfaces\Actions\ServiceParameterInterface::setGroup()
     */
    public function setGroup(string $value) : ServiceParameterInterface
    {
        $this->group = $value;
        return $this;
    }
}