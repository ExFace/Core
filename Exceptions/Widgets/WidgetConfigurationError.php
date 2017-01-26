<?php namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Exception thrown if a widget fails to read it's configuration or an invalid configuration value is passed.
 * 
 * This exception will be typically thrown by setters in the widget class. This way, configuration values being
 * set programmatically and via UXON import can be checked in the same manner. 
 *
 * @author Andrej Kabachnik
 *
 */
class WidgetConfigurationError extends RuntimeException implements WidgetExceptionInterface {
	
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