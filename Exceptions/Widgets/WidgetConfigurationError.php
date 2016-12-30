<?php namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;

class WidgetConfigurationError extends RuntimeException implements WidgetExceptionInterface {
	
	use WidgetExceptionTrait;
	
}