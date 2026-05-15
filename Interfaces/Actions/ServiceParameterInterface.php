<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\CommonLogic\Actions\ServiceParameter;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\CommonLogic\UxonObject;

/**
 * Interface for models of all kinds of parameters and arguments of remote service calls.
 * 
 * Service parameters are UXON prototypes describing arguments of external services: e.g. web services, RPC calls, CLI 
 * commands, etc. The parameter model must include everything needed to generate syntactically correct service calls.
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

    /**
     * @param bool $value
     * @return ServiceParameterInterface
     */
    public function setRequired(bool $value) : ServiceParameterInterface;
    
    public function isEmpty() : bool;
    
    public function isValidValue($val) : bool;
    
    /**
     * 
     * @param mixed $val
     * @throws DataTypeValidationError
     * @return string
     */
    public function parseValue($val) : string;
    
    /**
     * 
     * @return mixed
     */
    public function getDefaultValue();
    
    public function hasDefaultValue() : bool;
    
    /**
     * 
     * @param mixed $string
     * @return ServiceParameterInterface
     */
    public function setDefaultValue($string) : ServiceParameterInterface;

    /**
     * @return ExpressionInterface|null
     */
    public function getEmptyExpression() : ?ExpressionInterface;

    /**
     * The value to be used to indicate, that the parameter is empty (e.g. NULL instead of an empty string)
     * 
     * Accepted values:
     * 
     * - Number or boolean value (e.g. `1` or `false`)
     * - `null` or `=NullValue()` to indicate, that any empty value must be turned into `null`
     * - Quoted string (e.g. `'empty'`)
     * - Formula (e.g. `=Today()` or `=GetConfig()`) - calculation result will be used if empty value passed to parameter
     * 
     * @param ExpressionInterface|null|int|float|bool $stringOrExpression
     * @return ServiceParameterInterface
     */
    public function setEmptyExpression($stringOrExpression) : ServiceParameterInterface;
    
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
    
    /**
     * 
     * @return string
     */
    public function getDescription() : string;
    
    /**
     * 
     * @param string $value
     * @return ServiceParameterInterface
     */
    public function setDescription(string $value) : ServiceParameterInterface; 
    
    /**
     * Returns the group the parameter belongs to - if the action includes multiple parameter groups (e.g. CLI arguments and options)
     * 
     * @param string $default
     * @return string|NULL
     */
    public function getGroup(string $default = null) : ?string;
    
    /**
     * The group of the perameter in case tha action takes different parameter groups (e.g. CLI arguments and options)
     *
     * @param string $value
     * @return ServiceParameterInterface
     */
    public function setGroup(string $value) : ServiceParameterInterface;

    /**
     * Returns a single example value for this parameter or NULL if none defined.
     *
     * @return string|int|float|bool|null
     */
    public function getExample() : mixed;

    /**
     * Returns TRUE if an example value is defined for this parameter.
     *
     * @return bool
     */
    public function hasExample() : bool;

    /**
     * A single example value for this parameter (similar to OpenAPI's example field).
     *
     * @param string $value
     * @return ServiceParameterInterface
     */
    public function setExample(string $value) : ServiceParameterInterface;
}