<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Exceptions\UxonParserError;

class UxonObject extends \stdClass implements \IteratorAggregate {
	/**
	 * Returns true if there are not properties in the UXON object
	 * @return boolean
	 */
	public function is_empty(){
		$array = (array) $this;
		if (empty($array)){
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Returns the actual UXON code (in JSON notation). The output can be optionally prettified, improving human readability
	 * @param boolean $prettify
	 */
	public function to_json($prettify = false){
		$options = $prettify ? JSON_PRETTY_PRINT : null;
		return json_encode($this, $options);
	}
	
	/**
	 * Creates a UXON object from a JSON string
	 * @param unknown $uxon
	 * @return UxonObject
	 */
	public static function from_json($uxon){
		$obj = json_decode($uxon);
		$result = new self;
		if ($obj){
			foreach (get_object_vars($obj) as $var => $val){
				$result->set_property($var, $val);
			}
		}
		return $result;
	}
	
	/**
	 * Creates a UXON object from a standard class object (e.g. the result of json_decode())
	 * @param \stdClass $uxon
	 * @return UxonObject
	 */
	public static function from_stdClass(\stdClass $uxon){
		$result = new self;
		foreach (get_object_vars($uxon) as $var => $val){
			$result->set_property($var, $val);
		}
		return $result;
	}
	
	/**
	 * Creates a UXON object from an array. The resulting UXON will be an array itself, but alle elements will get transformed
	 * to UXON objects.
	 * @param array $uxon
	 * @return array
	 */
	public static function from_array(array $uxon){
		$result = array();
		foreach ($uxon as $var => $val){
			if (is_array($val)){
				$result[$var] = self::from_array($val);
			} elseif ($val instanceof \stdClass){
				$result[$var] = self::from_stdClass($val);
			} else {
				$result[$var] = $val;
			}
		}
		return $result;
	}
	
	/**
	 * Attempts to create a UxonObject autodetecting the type of input
	 * @param mixed $string_or_array_or_object
	 */
	public static function from_anything($string_or_array_or_object){
		if ($string_or_array_or_object instanceof UxonObject){
			return $string_or_array_or_object;
		} elseif (is_array($string_or_array_or_object)){
			return self::from_array($string_or_array_or_object);
		} elseif (is_object($string_or_array_or_object)){
			return self::from_stdClass($string_or_array_or_object);
		} else {
			return self::from_json($string_or_array_or_object);
		}
	}
	
	/**
	 * Returns a property specified by name (alternative to $uxon->name)
	 * @param string $name
	 */
	public function get_property($name){
		return $this->$name;
	}
	
	/**
	 * Returns all properties of this UXON object as an assotiative array
	 * @return array
	 */
	public function get_properties_all(){
		return get_object_vars($this);
	}
	
	/**
	 * Adds a property to the UXON object. Property values may be scalars, arrays, stdClasses or other UxonObjects
	 * @param string $property_name
	 * @param mixed $value_or_object_or_string
	 */
	public function set_property($property_name, $value_or_object_or_string){
		if (is_array($value_or_object_or_string)){
			$this->$property_name = UxonObject::from_array($value_or_object_or_string);
		} elseif (is_object($value_or_object_or_string) && !($value_or_object_or_string instanceof UxonObject)){
			$this->$property_name = UxonObject::from_stdClass($value_or_object_or_string);
		} else {
			$this->$property_name = $value_or_object_or_string;
		}
		return $this;
	}
	
	/**
	 * Extends this UXON object with properties of the given one. Conflicting properties will be overridden with
	 * values from the argument object!
	 * @param UxonObject $extend_by_uxon
	 * @return UxonObject
	 */
	public function extend(\stdClass $extend_by_uxon){
		// FIXME For some reason array_merge_recursive produces very strange nested arrays here if the second array
		// should overwrite values from the first one with the same value
		return self::from_stdClass((object) array_merge((array) $this, (array) $extend_by_uxon));
	}
	
	/**
	 * Returns a full copy of the UXON object. This is a deep copy including arrays, etc. in contrast to the built-in
	 * PHP clone.
	 * @return UxonObject
	 */
	public function copy(){
		return self::from_stdClass($this);
	}
	
	/**
	 * {@inheritDoc}
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator(){
		return new \ArrayIterator((array)$this);
	}
	
	/**
	 * This method will try to import this UXON object to a given business object instance (e.g. a widget, an action, etc.)
	 * using it's public setters. It is a generic alternative to a manually defined import_uxon_object() method.
	 * @param iCanBeConvertedToUxon $instance
	 * @param array $exclue_properties
	 * @throws UxonParserError
	 * @return UxonObject
	 */
	public function import_to_instance(iCanBeConvertedToUxon &$instance, $exclue_properties = array()){
		$vars = array_diff_key($this->get_properties_all(), array_flip($exclue_properties));
		foreach ($vars as $var => $val){
			if (method_exists($instance, 'set_'.$var)){
				call_user_func(array($instance, 'set_'.$var), $val);
			} else {
				throw new UxonParserError('Property "' . $var . '" cannot be automatically imported to "' . get_class($instance) . '": setter function not found!');
			}
		}
		return $this;
	}
	
	/**
	 * Removes the given property from the UXON object
	 * @param string $name
	 * @return \exface\Core\CommonLogic\UxonObject
	 */
	public function unset_property($name){
		unset($this->$name);
		return $this;
	}
}