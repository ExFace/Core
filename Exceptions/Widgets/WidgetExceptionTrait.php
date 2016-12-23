<?php namespace exface\Core\Exceptions\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\ExceptionTrait;

trait WidgetExceptionTrait {

	use ExceptionTrait {
		create_widget as create_parent_widget;
	}

	private $widget = null;

	public function __construct (WidgetInterface $widget, $message, $code = null, $previous = null) {
		parent::__construct($message, ($code ? $code : static::get_default_code()), $previous);
		$this->set_widget($widget);
	}

	public function get_widget(){
		return $this->widget;
	}

	public function set_widget(WidgetInterface $sheet){
		$this->widget = $sheet;
		return $this;
	}

}
?>