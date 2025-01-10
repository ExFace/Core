<?php
namespace exface\Core\CommonLogic\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\BadMethodCallException;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\DataTypeFactory;

/**
 * This trait includes everything needed for a enum data type with values defined in code
 * 
 * Enum values are defined as constants of the data type class. The enum values are the
 * constant values and the enum labels are to be computed in the concrete implementation
 * class if needed - e.g. via method `getLabels()` as seen in `ComparatorDataType`.
 * 
 * The constant names are not important for the enum - they are normally only used internally 
 * inside this trait or the implementing class.
 * 
 * When casting values, that differ in case, the case is automatically normalized to the 
 * version in the constant. Thus `::cast()` always returns the exact notation of the constant 
 * value.
 * 
 * The constant
 * 
 * @see SortingDirectionsDataType for an example
 * 
 */
trait EnumStaticDataTypeTrait {
    
    /**
     * Static cache for constants for each class: [[class] => [<CONSTANT_NAME_UC> => <constnat_value>]]
     *
     * @var array
     */
    protected static $cache = [];

    /**
     * Static cache for case-insensitive value search (find constant name by value): [[class] => [<CONSTANT_VALUE_UC> => <CONSTANT_NAME_UC>]]
     * 
     * These arrays have constant values for keys and constant names for values. For easier
     * case insensitive search, the constant values are all uppercased. If there are multiple
     * constants with the same value, the cache will contain the name of the first constant 
     * in order of definition.
     * 
     * @var array
     */
    private static $cacheValuesNC = [];
    
    /**
     * Returns all possible value-label pairs as an array
     *
     * @return array Constant name as key, constant value as value
     */
    public static function getValuesStatic()
    {
        $class = get_called_class();
        if (! array_key_exists($class, static::$cache)) {
            $reflection = new \ReflectionClass($class);
            static::$cache[$class] = $reflection->getConstants();
        }
        
        return static::$cache[$class];
    }
    
    /**
     * Returns the names of the constants (keys of the value cache array) as a numeric array.
     * 
     * @return array
     */
    public static function getConstantNames()
    {
        return array_keys(static::getValuesStatic());
    }
    
    /**
     * Returns the constant name matching the given value (case insensitive) or FALSE 
     * if the value does not match any constant.
     * 
     * The search is case insensitive!
     * 
     * @param string|int| $label
     * @return string|int|null|false
     */
    public static function findConstant($value)
    {
        $class = get_called_class();
        if (! array_key_exists($class, static::$cacheValuesNC)) {
            // Store reverse cache. Note, that array_flip will keep only the last key of the
            // original array in case there were multiple keys with the same value. Since we
            // need the first key to match, we first reverse the array, than flip it and then
            // uppercase all keys.
            static::$cacheValuesNC[$class] = array_change_key_case(array_flip(array_reverse(static::getValuesStatic())), CASE_UPPER);
        }

        // Search over the case-insensitive cache
        if (is_string($value) === true) {
            $value = mb_strtoupper($value);
        }

        // Since the values are the constant names in this case, there cannot be 
        // a value equal to NULL - that would meen, there is no constant, so
        // return false in this case
        return static::$cacheValuesNC[$class][$value] ?? false;
    }
    
    /**
     * Check if $value is part of the enum
     * 
     * This returns TRUE if there is a constant with a value matching the given $value.
     *
     * @param string|int|null $value
     *
     * @return bool
     */
    public static function isValidStaticValue($value)
    {   
        // See if there is an exact case-sensitive match first. If not, try to find
        // the constant name with an case-insensitive search. Since there are really
        // a lot of validations on static enums like ComparatorDataType, this should
        // speed up the "regular" cases.
        $exactMatch = in_array($value, static::getValuesStatic());
        if ($exactMatch === false) {
            return static::findConstant($value) !== false;
        }
        return true;
    }
    
    /**
     * Returns a value when called statically like so: MyEnum::SOME_VALUE() given SOME_VALUE is a class constant
     *
     * @param string $name
     * @param array $arguments
     *
     * @return static
     * @throws BadMethodCallException
     */
    public static function __callStatic($name, $arguments)
    {
        $array = static::getValuesStatic();
        if (isset($array[$name])) {
            if (! ($arguments[0] instanceof Workbench)) {
                throw new BadMethodCallException("Argument 1 passed to " . get_called_class() . "::" . $name . "() must implement interface \exface\Core\CommonLogic\Workbench, " . gettype($arguments[0]) . " given!");
            }
            $selector = SelectorFactory::createDataTypeSelector($arguments[0], static::class);
            return new static($selector, $array[$name]);
        }
        
        throw new BadMethodCallException("No static method or enum constant '$name' in class " . get_called_class());
    }
    
    /**
     *
     * {@inheritdoc}
     * @see AbstractDataType::cast()
     */
    public static function cast($value)
    {
        // Cast according to the base type - e.g. number or string
        $value = parent::cast($value);
        if (is_string($value)) {
            $value = trim($value);
        }
        
        // Check if the casted value is part of the enum
        $constName = static::findConstant($value);
        
        // Convert all sorts of empty values to NULL except if they are explicitly
        // part of the enumeration: e.g. an empty string should become null if the
        // enumeration does not include the empty string explicitly.
        // TODO #null-or-NULL does the NULL constant need to pass casting?
        if ((static::isValueEmpty($value) === true || static::isValueLogicalNull($value)) && $constName === false) {
            return null;
        }
        
        if ($constName === false){
            throw new DataTypeCastingError('Value "' . $value . '" does not fit into the enumeration data type ' . get_called_class() . '!');
        }
        
        return static::getValuesStatic()[$constName];
    }    
    
    /**
     * 
     * @param mixed $value
     * @return bool
     */
    public function isValidValue($value) : bool
    {
        return static::isValidStaticValue($value);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getValues()
    {
        return $this::getValuesStatic();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::setValues()
     */
    public function setValues($uxon_or_array)
    {
        throw new DataTypeConfigurationError($this, 'Cannot override values in static enumeration data type ' . $this->getAliasWithNamespace() . '!', '6XGNBJB');
    }   

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getValueHints()
     */
    public function getValueHints() : array
    {
        return [];
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $value
     * @return \exface\Core\Interfaces\ValueObjectInterface
     */
    public static function fromValue(WorkbenchInterface $workbench, string $value)
    {
        return DataTypeFactory::createFromPrototype($workbench, __CLASS__)->withValue($value);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::toArray()
     */
    public function toArray() : array
    {
        return $this->getValues();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabelOfValue()
     */
    public function getLabelOfValue($value = null) : ?string
    {
        $value = $value ?? $this->getValue();
        $labels = $this->getLabels();
        $label = $labels[$value] ?? null;
        if ($label === null) {
            foreach ($labels as $key => $labelValue) {
                if (strcasecmp($labelValue, $key) === 0) {
                    return $labelValue;
                }
            }
        }
        return $label;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getHintOfValue()
     */
    public function getHintOfValue($value) : ?string
    {
        return null;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see AbstractDataType::format()
     */
    public function format($value = null) : string
    {
        $value = parent::format($value);
        if ($value === '') {
            return '';
        }
        return $this->getLabelOfValue($value) ?? $value;
    }
}