<?php namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Exception thrown if trying to set a widget property, that does not exist.
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetPropertyUnknownError extends InvalidArgumentException implements WidgetExceptionInterface {
	
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