<?php namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\DataSources\WidgetExceptionInterface;

/**
 * Exception thrown if trying to set a widget property, that does not exist.
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetPropertyUnknownError extends InvalidArgumentException implements WidgetExceptionInterface {
	
	use WidgetExceptionTrait;
	
}