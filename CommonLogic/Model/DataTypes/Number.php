<?php
namespace exface\Core\CommonLogic\Model\DataTypes;
use exface\Core\Exceptions\DataTypeValidationError;

class Number extends AbstractDataType {
	
	public static function parse($string){
		if (is_numeric($string)){
			return $string;
		} else {
			throw new DataTypeValidationError('Cannot convert "' . $string . '" to a number!');
			return '';
		}
	}
}
?>