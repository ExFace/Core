<?php
namespace exface\Core\CommonLogic\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\BadMethodCallException;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;

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
     * Check if is valid enum value
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function isValidStaticValue($value)
    {
        return in_array($value, static::getValuesStatic(), true);
    }
    
    /**
     * Returns a value when called statically like so: MyEnum::SOME_VALUE() given SOME_VALUE is a class constant
     *
     * @param string $name
     * @param array  $arguments
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
            return new static($arguments[0], $array[$name]);
        }
        
        throw new BadMethodCallException("No static method or enum constant '$name' in class " . get_called_class());
    }
    
    
    public static function cast($value)
    {
        $value = parent::cast($value);
        
        if (! static::isValidStaticValue($value)){
            throw new DataTypeCastingError('Value "' . $value . '" does not fit into the enumeration data type ' . get_called_class() . '!');
        }
        
        return $value;
    }    
    
    public function isValidValue($value)
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
        return $this->getValuesStatic();
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
}