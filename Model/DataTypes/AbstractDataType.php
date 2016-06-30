<?php
namespace exface\Core\Model\DataTypes;
use exface\Core\Exceptions\DataTypeValidationError;

abstract class AbstractDataType {
	private $exface = null;
	private $name = null;
	
	public function __construct(&$exface){
		$this->exface = $exface;
	}
	
	public function get_model(){
		return $this->exface()->model;
	}
	
	public function exface(){
		return $this->exface;
	}
	
	/**
	 * Returns the string name of the data type (e.g. Number, String, etc.)
	 * @return string
	 */
	public function get_name(){
		if (is_null($this->name)){
			$this->name = substr(get_class($this), (strrpos(get_class($this), DIRECTORY_SEPARATOR)+1));
		}
		return $this->name;
	}
	
	/**
	 * Returns TRUE if the current data type is derived from the given one (e.g. Integer::is(Number) = true) and FALSE otherwise.
	 * 
	 * @param AbstractDataType | string $data_type_or_string
	 * @return boolean
	 */
	public function is($data_type_or_string){
		if ($data_type_or_string instanceof AbstractDataType){
			$class = get_class($data_type_or_string);
		} else {
			$class = __NAMESPACE__ . '\\' . $data_type_or_string;
		}
		return ($this instanceof $class);
	}
	
	/**
	 * Returns a normalized representation of the given string, that can be interpreted by the ExFace core correctly.
	 * E.g. Date::parse('21.9.1984') = 1984-09-21
	 * 
	 * @param string $string
	 * @throws DataTypeValidationError
	 * @return string
	 */
	public static function parse($string){
		return $string;
	}
}
?>