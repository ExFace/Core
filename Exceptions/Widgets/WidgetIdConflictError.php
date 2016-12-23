<?php namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\DataSources\WidgetExceptionInterface;

class WidgetIdConflictError extends InvalidArgumentException implements WidgetExceptionInterface {
	
	use WidgetExceptionTrait;
	
	public static function get_default_code(){
		return '6T6I51G';
	}
	
}