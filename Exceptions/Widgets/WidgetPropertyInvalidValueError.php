<?php namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;
use exface\Core\Interfaces\WidgetInterface;

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
	
	/**
	 *
	 * @param WidgetInterface $widget
	 * @param string $message
	 * @param string $alias
	 * @param \Throwable $previous
	 */
	public function __construct (WidgetInterface $widget, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_widget($widget);
	}
	
}