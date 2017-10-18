<?php
namespace exface\Core\CommonLogic\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;

trait EnumDataTypeTrait {
    
    /**
     * Store existing constants in a static cache per object.
     *
     * @var array
     */
    protected static $cache = array();
    
    /**
     * Returns the enum key (i.e. the constant name).
     *
     * @return mixed
     */
    public function getValueKey()
    {
        return static::getEnumKey($this->value);
    }
    
    /**
     * Returns the names (keys) of all constants in the Enum class
     *
     * @return array
     */
    public static function getEnumKeys()
    {
        return array_keys(static::getEnumArray());
    }
    
    /**
     * Returns instances of the Enum class of all Enum constants
     *
     * @return static[] Constant name in key, Enum instance in value
     */
    public static function getEnumValues()
    {
        $values = array();
        
        foreach (static::getEnumArray() as $key => $value) {
            $values[$key] = new static($value);
        }
        
        return $values;
    }
    
    /**
     * Returns all possible values as an array
     *
     * @return array Constant name in key, constant value in value
     */
    public static function getEnumArray()
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
     * @param $value
     *
     * @return bool
     */
    protected static function isValidValue($value)
    {
        return in_array($value, static::getEnumArray(), true);
    }
    
    /**
     * Check if is valid enum key
     *
     * @param $key
     *
     * @return bool
     */
    protected static function isValidKey($key)
    {
        $array = static::getEnumArray();
        
        return isset($array[$key]);
    }
    
    /**
     * Return key for value
     *
     * @param $value
     *
     * @return mixed
     */
    public static function getEnumKey($value)
    {
        return array_search($value, static::getEnumArray(), true);
    }
    
    /**
     * Returns a value when called statically like so: MyEnum::SOME_VALUE() given SOME_VALUE is a class constant
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return static
     * @throws \BadMethodCallException
     */
    public static function __callStatic($name, $arguments)
    {
        $array = static::getEnumArray();
        if (isset($array[$name])) {
            return new static($array[$name]);
        }
        
        throw new \BadMethodCallException("No static method or enum constant '$name' in class " . get_called_class());
    }
    
    public static function cast($value)
    {
        $value = parent::cast($value);
        
        if (! static::isValidValue($value)){
            throw new DataTypeCastingError('Value "' . $value . '" does not fit into the enumeration data type ' . get_called_class() . '!');
        }
        
        return $value;
    }
    
    public function parse($value)
    {
        $value = parent::cast($value);
        
        if (! $this->isValidValue($value)){
            throw new DataTypeCastingError('Value "' . $value . '" does not fit into the enumeration data type ' . get_called_class() . '!');
        }
        
        return $value;
    }
    
    public function setValues($uxon_object_or_array)
    {
        $array = [];
        if ($uxon_object_or_array instanceof UxonObject) {
            $array = $uxon_object_or_array->toArray();
        } elseif (is_array($uxon_object_or_array)) {
            $array = $uxon_object_or_array;
        } else {
            throw new DataTypeConfigurationError($this, 'Invalid format "' . gettype($uxon_object_or_array) . '" for enumeration values given: expecting a UXON array or a plain array!');
        }
        
        foreach ($array as $key => $value) {
            define(__CLASS__ . ':' . $key, $value);
        }
        
        return $this;
    }
    
}