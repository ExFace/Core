<?php namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\DataSources\WidgetExceptionInterface;
use exface\Core\Exceptions\RuntimeException;

class WidgetConfigurationError extends RuntimeException implements WidgetExceptionInterface {
	
	use WidgetExceptionTrait;
	
}