<?php
namespace exface\Core\DataTypes;
class StringDataType extends AbstractDataType {
	/**
	 * Converts a string from under_score (snake_case) to camelCase.
	 * 
	 * @param string $string
	 * @return string
	 */
	public static function convert_case_underscore_to_camel($string){
		return lcfirst(static::convert_case_underscore_to_pascal($string));
	}
	
	/**
	 * Converts a string from camelCase to under_score (snake_case).
	 * @param string $string
	 * @return string
	 */
	public static function convert_case_camel_to_underscore($string){
		return static::convert_case_pascal_to_underscore($string);
	}
	
	/**
	 * Converts a string from under_score (snake_case) to PascalCase.
	 *
	 * @param string $string
	 * @return string
	 */
	public static function convert_case_underscore_to_pascal($string){
		return str_replace('_', '', ucwords($string, "_"));
	}
	
	/**
	 * Converts a string from PascalCase to under_score (snake_case).
	 * @param string $string
	 * @return string
	 */
	public static function convert_case_pascal_to_underscore($string){
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
	}
	
	/**
	 * 
	 * @param string $haystack
	 * @param string $needle
	 * @param boolean $case_sensitive
	 * @return boolean
	 */
	public static function starts_with($haystack, $needle, $case_sensitive = true){
		if ($case_sensitive){
			return substr($haystack, 0, strlen($needle)) === $needle;
		} else {
			return substr(mb_strtoupper($haystack), 0, strlen(mb_strtoupper($needle))) === mb_strtoupper($needle);
		}
	}
}
?>