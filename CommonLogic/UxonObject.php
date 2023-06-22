<?php
namespace exface\Core\CommonLogic;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Exceptions\LogicException;
use exface\Core\Exceptions\UxonSyntaxError;

class UxonObject implements \IteratorAggregate
{
    private $array = [];
    
    private $childUxons = [];
    
    public function __construct(array $properties = [])
    {
        $this->array = $this->stripComments($properties);
    }
    
    /**
     * 
     * @param array $properties
     * @return array
     */
    protected function stripComments(array $properties) : array
    {
        $skip = false;
        foreach ($properties as $prop => $val) {
            $prefix = mb_substr($prop, 0, 2);
            switch ($prefix) {
                case '//': unset($properties[$prop]); break;
                case '/*': unset($properties[$prop]); $skip = true; break;
                case '*/':  unset($properties[$prop]); $skip = false; break;
            }
            if ($skip === true) {
                unset($properties[$prop]);
            }
            if (is_array($val) && array_key_first($val) === '/*' && array_key_last($val) === '*/') {
                unset($properties[$prop]);
            }
        }
        return $properties;
    }

    /**
     * Returns true if there are not properties in the UXON object
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return empty($this->array) ? true : false;
    }
    
    public function isPropertyEmpty($property_name)
    {
        return empty($this->array[$property_name]) ? true : false;
    }

    /**
     * Returns the actual UXON code (in JSON notation).
     * The output can be optionally prettified, improving human readability
     *
     * @param boolean $prettify            
     */
    public function toJson($prettify = false)
    {
        $options = $prettify ? JSON_PRETTY_PRINT : null;
        // Force number to be numbers and not numeric strings to make sure
        // the JSON looks the same on different systems (e.g. Microsoft IIS would
        // otherwise use numbers and Apache - numeric strings)
        $options = $options | JSON_NUMERIC_CHECK;
        return json_encode($this->toArray(), $options);
    }

    /**
     * Creates a UXON object from a JSON string.
     * 
     * The argument $normalizeKeyCase can be set to CASE_UPPER or CASE_LOWER to normalize all keys.
     *
     * @param string $uxon      
     * @param int $normalizeKeyCase      
     * @return UxonObject
     */
    public static function fromJson($uxon, int $normalizeKeyCase = null)
    {
        $array = json_decode(trim($uxon), true);
        if (is_array($array)){
            return static::fromArray($array, $normalizeKeyCase);
        } else {
            if ($uxon !== '' && $uxon !== null) {
                throw new UxonSyntaxError('Cannot parse string "' . substr($uxon, 0, 50) . '" as UXON: ' . json_last_error_msg() . ' in JSON decoder!', null, null, $uxon);
            }
            return new self();
        }
    }

    /**
     * Creates a UXON object from an array.
     * 
     * The argument $normalizeKeyCase can be set to CASE_UPPER or CASE_LOWER to normalize all keys.
     *
     * @param array $array   
     * @param int $normalizeKeyCase         
     * @return UxonObject
     */
    public static function fromArray(array $array, int $normalizeKeyCase = null)
    {
        if ($normalizeKeyCase !== null) {
            $array = array_change_key_case($array, $normalizeKeyCase);
        }
        return new self($array);
    }

    /**
     * Attempts to create a UxonObject autodetecting the type of input
     *
     * @param mixed $string_or_array_or_object            
     * @return UxonObject
     */
    public static function fromAnything($string_or_array_or_object)
    {
        if ($string_or_array_or_object instanceof UxonObject) {
            return $string_or_array_or_object->copy();
        } elseif (is_array($string_or_array_or_object)) {
            return self::fromArray($string_or_array_or_object);
        } else {
            return self::fromJson($string_or_array_or_object);
        }
    }

    /**
     * Returns a property specified by name (alternative to $uxon->name)
     *
     * @param string $name            
     * @return mixed
     */
    public function getProperty($name)
    {
        $val = $this->array[$name] ?? null;
        if (is_array($val) === true) {
            $child = $this->childUxons[$name] ?? null;
            if (null === $child) {
                $child = $this->childUxons[$name] = new self($val);
            } 
            return $child;
        }
        return $val;
    }

    /**
     *
     * @param string $name            
     * @return boolean
     */
    public function hasProperty($name)
    {
        return array_key_exists($name, $this->array);
    }

    /**
     *
     * @param string $name            
     * @param boolean $case_sensitive            
     * @return mixed
     */
    public function findPropertyKey($name, $case_sensitive = false)
    {
        if ($this->hasProperty($name)) {
            return $name;
        } else {
            $property_names = array_keys($this->array);
            foreach ($property_names as $property_name) {
                if (strcasecmp($name, $property_name) === 0){
                    return $property_name;
                }
            }
            return false;
        }
    }
    
    /**
     * Returns the key holding the given value or FALSE if nothing was found.
     * 
     * @param mixed $value
     * 
     * @return string|boolean
     */
    public function search($value)
    {
        return array_search($value, $this->array, true);
    }

    /**
     * Returns all properties of this UXON object as an assotiative array
     *
     * @return array
     */
    public function getPropertiesAll()
    {
        $array = [];
        foreach (array_keys($this->array) as $var){
            $array[$var] = $this->getProperty($var);
        }
        return $array;
    }

    /**
     * Adds a property to the UXON object.
     * Property values may be scalars, arrays, stdClasses or other UxonObjects
     *
     * @param string $property_name            
     * @param UxonObject|string $scalar_or_uxon   
     * @return \exface\Core\CommonLogic\UxonObject         
     */
    public function setProperty($property_name, $scalar_or_uxon)
    {
        $this->array[$property_name] = $this->normalizeValue($scalar_or_uxon);
        if (array_key_exists($property_name, $this->childUxons)) {
            unset($this->childUxons[$property_name]);
        }
        return $this;
    }
    
    /**
     * 
     * @param mixed $scalar_or_uxon
     * @return string|number|bool|array
     */
    protected function normalizeValue($scalar_or_uxon)
    {
        return $scalar_or_uxon instanceof UxonObject ? $scalar_or_uxon->toArray() : $scalar_or_uxon;
    }
    
    /**
     * 
     * @param string $property_name
     * @param UxonObject|string $scalar_or_uxon
     * @throws UxonParserError
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function appendToProperty($property_name, $scalar_or_uxon)
    {
        if (! isset($this->array[$property_name])) {
            $this->array[$property_name] = [];
        } elseif (is_scalar($this->array[$property_name])){
            throw new UxonParserError($this, 'Cannot append "' . $scalar_or_uxon . '" to UXON property "' . $property_name . '": the property is a of a scalar type!');
        }
        $this->array[$property_name][] = $this->normalizeValue($scalar_or_uxon);
        
        if (array_key_exists($property_name, $this->childUxons)) {
            unset($this->childUxons[$property_name]);
        }
        
        return $this;
    }
    
    public function append($scalar_or_uxon)
    {
        $this->array[] = $this->normalizeValue($scalar_or_uxon);
        return $this;
    }
    
    public function countProperties()
    {
        return count($this->array);
    }

    /**
     * Returns a copy of UXON object extended with properties of the given one.
     * Conflicting properties will be overridden with values from the argument object!
     *
     * @param UxonObject $extend_by_uxon            
     * @return UxonObject
     */
    public function extend(UxonObject $extend_by_uxon) : UxonObject
    {
        return new self(array_replace_recursive($this->array, $extend_by_uxon->toArray()));
    }
    
    /**
     * Returns a new UXON object containing only properties matching the provided array
     * 
     * @param string[] $properties
     * @return UxonObject
     */
    public function extract(array $properties) : UxonObject
    {
        $old = $this->toArray();
        $new = [];
        foreach ($properties as $key) {
            if (array_key_exists($key, $old)) {
                $new[$key] = $old[$key];
            }
        }
        return new self($new);
    }

    /**
     * Returns a full copy of the UXON object.
     * This is a deep copy including arrays, etc. in contrast to the built-in
     * PHP clone.
     *
     * @return UxonObject
     */
    public function copy() : self
    {
        return new self($this->array);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \IteratorAggregate::getIterator()
     */
    public function getIterator() : \Traversable
    {
        return new \ArrayIterator($this->getPropertiesAll());
    }

    /**
     * Removes the given property from the UXON object
     *
     * @param string $name            
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function unsetProperty($name)
    {
        unset($this->array[$name]);
        if (array_key_exists($name, $this->childUxons)) {
            unset($this->childUxons[$name]);
        }
        return $this;
    }

    /**
     * Converts the UXON object ot an array optionally normalizing key case
     * 
     * The argument $normalizeKeyCase can be set to CASE_UPPER or CASE_LOWER to normalize all keys.
     * 
     * @param int $normalizeKeyCase
     * @return array
     */
    public function toArray(int $normalizeKeyCase = null)
    {
        if ($normalizeKeyCase !== null) {
            return array_change_key_case($this->array, $normalizeKeyCase);
        }
        return $this->array;
    }

    /**
     * Finds public setter methods in the given class mathing properties of this UXON object and calls them for each property.
     *
     * NOTE: this only works with public setters as private and protected methods cannot be called from the UXON object. To
     * work with non-public setters use the ImportUxonObjectTrait in your enitity!
     *
     * @param object $target_class_instance            
     * @throws UxonMapError
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function mapToClassSetters($target_class_instance)
    {
        if (! is_object($target_class_instance)) {
            throw new UxonMapError($this, 'Cannot import UXON configuration to "' . gettype($target_class_instance) . '": only instantiated PHP classes supported!');
        }
        
        foreach (array_keys($this->array) as $var) {
            $setterCamelCased = 'set' . StringDataType::convertCaseUnderscoreToPascal($var);
            if (method_exists($target_class_instance, $setterCamelCased)) {
                call_user_func(array(
                    $target_class_instance,
                    $setterCamelCased
                ), $this->getProperty($var));
            } else {
                throw new UxonMapError($this, 'No setter method found for UXON property "' . $var . '" in "' . get_class($target_class_instance) . '"!');
            }
        }
        return $this;
    }
    
    public function isArray($ignore_gaps_in_keys = false)
    {
        if ($this->isEmpty()){
            return true;
        }
        
        foreach (array_keys($this->array) as $key){
            if (!is_numeric($key)){
                return false;
            }
        }
        
        if ($ignore_gaps_in_keys == false){
            return array_keys($this->array) === range(0, count($this->array) - 1);
        }
        
        return true;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getPropertyNames() : array
    {
        return array_keys($this->array);
    }
    
    public function __get($name)
    {
        throw new LogicException('Direct access to properties of a UxonObject is not supported anymore!');
    }
    
    public function __set($name, $value)
    {
        throw new LogicException('Direct access to properties of a UxonObject is not supported anymore!');
    }
    
    /**
     * Returns a copy of the UXON with certain properties removed.
     * 
     * @param string[] $propertyNames
     * @return UxonObject
     */
    public function withPropertiesRemoved(array $propertyNames) : UxonObject
    {
        $array = $this->array;
        
        if (empty($array)) {
            return new UxonObject();
        }
        
        $result = [];
        $propertyNames = array_map('mb_strtolower', $propertyNames);
        
        foreach ($array as $key => $value) {
            if (! in_array(mb_strtolower($key), $propertyNames)) {
                if (is_array($value)) {
                    $result[$key] = (new UxonObject($value))->withPropertiesRemoved($propertyNames)->toArray();
                } else {
                    $result[$key] = $value;
                }
            }
        }
        
        return new UxonObject($result);
    }
}