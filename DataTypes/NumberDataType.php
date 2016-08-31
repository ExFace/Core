<?php
namespace exface\Core\DataTypes;
use exface\Core\Exceptions\DataTypeValidationError;

class NumberDataType extends AbstractDataType {
	
	public static function parse($string){
		if (is_numeric($string)){
			// Decimal numbers
			return $string;
		} elseif (strpos($string, '0x') === 0) {
			// Hexadecimal numbers in '0x....'-Notation
			return $string;
		} else {
			$matches = array();
			preg_match_all('!-?\d+[,\.]?\d*+!', str_replace(' ', '', $string), $matches);
			if (is_numeric($matches[0][0])){
				return $matches[0][0];
			}			
			throw new DataTypeValidationError('Cannot convert "' . $string . '" to a number!');
			return '';
		}
	}
}
?>