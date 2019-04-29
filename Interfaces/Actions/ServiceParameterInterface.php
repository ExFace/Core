<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\CommonLogic\UxonObject;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
interface ServiceParameterInterface extends  iCanBeConvertedToUxon, WorkbenchDependantInterface
{
    /**
     *
     * @return string
     */
    public function getName() : string;
    
    /**
     *
     * @return DataTypeInterface
     */
    public function getDataType() : DataTypeInterface;
    
    /**
     *
     * @return bool
     */
    public function isRequired() : bool;
    
    public function isEmpty() : bool;
    
    public function getAction() : iCallService;
    
    public function isValidValue($val) : bool;
    
    /**
     * 
     * @param mixed $val
     * @throws DataTypeValidationError
     * @return string
     */
    public function parseValue($val) : string;
    
    public function getDefaultValue() : string;
    
    public function hasDefaultValue() : bool;
    
    public function setDefaultValue(string $string) : ServiceParameterInterface;
    
    /**
     *
     * @return UxonObject
     */
    public function getCustomProperties() : UxonObject;
    
    /**
     * Custom parameter properties (similar to data address settings in attributes).
     *
     * @uxon-property custom_properties
     * @uxon-type object
     * @uxon-template {"": ""}
     *
     * @param UxonObject $value
     * @return ServiceParameterInterface
     */
    public function setCustomProperties(UxonObject $value) : ServiceParameterInterface;
    
    /**
     *
     * @param string $name
     * @return string|NULL
     */
    public function getCustomProperty(string $name) : ?string;
}