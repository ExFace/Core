<?php
namespace exface\Core\DataTypes;
class BooleanDataType extends AbstractDataType {
	
	public static function parse($string){
		return filter_var($string, FILTER_VALIDATE_BOOLEAN);
	}
}
?>