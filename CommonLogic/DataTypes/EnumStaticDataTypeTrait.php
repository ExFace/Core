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
 * Enum values are defined as constants of the data type class. The constant name is the
 * key and the value of the constant ist he value. When casting values, that differ in
 * case, the case is automatically normalized to the version in the constant. Thus
 * `::cast()` always returns the exact notation of the constant value.
 * 
 * @see SortingDirectionsDataType for an example
 * 
 */
trait EnumStaticDataTypeTrait {
    
    /**
     * Store existing constants in a static cache per object.
     *
     * @var array
     */
    protected static $cache = array();
    
    /**
     * Returns all possible value-label pairs as an array
     *
     * @return array Constant name in key, constant value in value
     */
    public static function getValuesStatic()
    {
        $class = get_called_class();
        if (!array_key_exists($class, static::$cache)) {
            $reflection            = new \ReflectionClass($class);
            static::$cache[$class] = $reflection->getConstants();
        }
        
        return static::$cache[$class];
    }
    
    /**
     * Returns the keys of the static values (the names of the constants) as a numeric array.
     * 
     * @return array
     */
    public static function getKeysStatic()
    {
        return array_keys(static::getValuesStatic());
    }
    
    /**
     * Returns the key (constant name) matching the given value or FALSE if the value 
     * does not match any key.
     * 
     * @param string $value
     * @return string|false
     */
    public static function findKey($value)
    {
        $found = array_search($value, static::getValuesStatic());
        if ($found === false) {
            return static::findKeyCaseInsensitive($value);
        }
        return $found;
    }

    /**
     * 
     * @param mixed $label
     * @return mixed|false
     */
    protected static function findKeyCaseInsensitive($label)
    {
        if ($label === null) {
            return static::getValuesStatic()[null] ?? null;
        }
        foreach (static::getValuesStatic() as $key => $name) {
            if (strcasecmp($label, $name) === 0) {
                return $key;
            }
        }
        return false;
    }
    
    /**
     * Check if is valid enum value
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function isValidStaticValue($value)
    {
        $found = in_array($value, static::getValuesStatic());
        if ($found === false) {
            return static::findKeyCaseInsensitive($value) !== false;
        }
        return $found;
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
        $key = static::findKey($value);
        
        // Convert all sorts of empty values to NULL except if they are explicitly
        // part of the enumeration: e.g. an empty string should become null if the
        // enumeration does not include the empty string explicitly.
        // TODO #null-or-NULL does the NULL constant need to pass casting?
        if ((static::isValueEmpty($value) === true || static::isValueLogicalNull($value)) && $key === false) {
            return null;
        }
        
        if ($key === false){
            throw new DataTypeCastingError('Value "' . $value . '" does not fit into the enumeration data type ' . get_called_class() . '!');
        }
        
        return static::getValuesStatic()[$key];
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
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabelOfValues()
     */
    public function getLabelOfValue($value = null) : ?string
    {
        $value = $value ?? $this->getValue();
        $labels = $this->getLabels();
        $label = $labels[$value] ?? null;
        if ($label === null) {
            foreach ($labels as $key => $label) {
                if (strcasecmp($value, $key) === 0) {
                    return $label;
                }
            }
        }
        return $label;
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