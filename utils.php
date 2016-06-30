<?php
namespace exface\Core;
use \DeepCopy\DeepCopy;
use \DeepCopy\Matcher\PropertyNameMatcher;
use \DeepCopy\Filter\KeepFilter;
class utils {
	public function deep_copy($object, array $properties_to_keep = null){
		$deepCopy = new DeepCopy();
		$deepCopy->addFilter(new KeepFilter(), new PropertyNameMatcher('exface'));
		if (is_array($properties_to_keep)){
			foreach ($properties_to_keep as $prop){
				$deepCopy->addFilter(new KeepFilter(), new PropertyNameMatcher($prop));
			}
		}
		return $deepCopy->copy($object);
	}
	
	/**
	 * Returns an array of ExFace-placeholders found in a string. 
	 * E.g. will return ["name", "id"] for string "Object [#name#] has the id [#id#]"
	 * 
	 * @param string $string
	 * @return array
	 */
	public function find_placeholders_in_string($string){
		$placeholders = array();
		preg_match_all("/\[#([^\]\[#]+)#\]/", $string , $placeholders);
		return is_array($placeholders[1]) ? $placeholders[1] : array();
	}
}
?>