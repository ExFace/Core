<?php namespace exface\Core\Exceptions\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;

/**
 * This trait enables an exception to output widget specific debug information.
 *
 * @author Andrej Kabachnik
 *
 */
trait WidgetExceptionTrait {

	use ExceptionTrait {
		create_widget as create_parent_widget;
	}

	private $widget = null;

	public function __construct (WidgetInterface $widget, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_widget($widget);
	}
	
	/**
	 * Returns the widget, that produced the error.
	 * 
	 * @return \exface\Core\Interfaces\WidgetInterface
	 */
	public function get_widget(){
		return $this->widget;
	}
	
	/**
	 * Sets the widget, that produced the error.
	 * 
	 * @param WidgetInterface $widget
	 * @return \exface\Core\Exceptions\Widgets\WidgetExceptionTrait
	 */
	public function set_widget(WidgetInterface $widget){
		$this->widget = $widget;
		return $this;
	}
	
	public function create_debug_widget(DebugMessage $debug_widget){
		$page = $debug_widget->get_page();
		$uxon_tab = $debug_widget->create_tab();
		$uxon_tab->set_caption('UXON');
		$request_widget = WidgetFactory::create($page, 'Html');
		$uxon_tab->add_widget($request_widget);
		$request_widget->set_value('<pre>' . $this->get_widget()->export_uxon_object()->to_json(true) . '</pre>');
		$debug_widget->add_tab($uxon_tab);
		return $debug_widget;
	}

}
?>