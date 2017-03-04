<?php
namespace exface\Core\DataTypes;
class BooleanDataType extends AbstractDataType {
	
	public static function parse($string){
		if (is_null($string)) return null;
		return filter_var($string, FILTER_VALIDATE_BOOLEAN);
	}
}
?>