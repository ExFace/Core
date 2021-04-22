<?php
namespace exface\Core\CommonLogic\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\BadMethodCallException;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\LogicException;

trait EnumStaticDataTypeTrait {
    
    /**
     * Store existing constants in a static cache per object.
     *
     * @var array
     */
    protected static $cache = array();
    
    /**
     * Returns all possible values as an array
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
     * Returns the keys of the static values (the names of the constants) as an array.
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
        return array_search($value, static::getValuesStatic());
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
        return in_array($value, static::getValuesStatic());
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
    
    
    public static function cast($value)
    {
        if (static::isValueEmpty($value) === true) {
            // Let the parent data type (e.g. string or number) handle empty values
            return parent::cast($value);
        }
        
        if ($value === EXF_LOGICAL_NULL) {
            return $value;
        }
        
        $value = parent::cast($value);
        $value = trim($value);
        
        if (! static::isValidStaticValue($value)){
            throw new DataTypeCastingError('Value "' . $value . '" does not fit into the enumeration data type ' . get_called_class() . '!');
        }
        
        return $value;
    }    
    
    /**
     * 
     * @param unknown $value
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
    
    public function getLabelOfValue($value = null) : string
    {
        $value = $value ?? $this->getValue();
        if ($value === null) {
            throw new LogicException('Cannot get text label for an enumeration value: neither an internal value exists, nor is one passed as parameter');
        }
        return $this->getLabels()[$value];
    }
}