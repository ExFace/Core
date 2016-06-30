<?php
namespace exface\Core\Model\DataTypes;
use exface\Core\Exceptions\DataTypeValidationError;

class Timestamp extends Date {
	public static function parse($string){
		try {
			$date = new \DateTime($string);
		} catch (\Exception $e){
			throw new DataTypeValidationError('Cannot convert "' . $string . '" to a date!', null, $e);
		}
		return $date->format('Y-m-d H:i:s');
	}
}
?>