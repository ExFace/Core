<?php namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\DataSources\WidgetExceptionInterface;
use exface\Core\Exceptions\UnexpectedValueException;

/**
 * Exception thrown if a widget property is being set to an invalid value. 
 * 
 * This exception is generally used to indicate setter-errors.
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetPropertyInvalidValueError extends UnexpectedValueException implements WidgetExceptionInterface {
	
	use WidgetExceptionTrait;
	
}