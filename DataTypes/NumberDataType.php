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
			throw new DataTypeValidationError('Cannot convert "' . $string . '" to a number!');
			return '';
		}
	}
}
?>