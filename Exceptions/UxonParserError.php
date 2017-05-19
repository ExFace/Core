<?php
namespace exface\Core\Exceptions;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Exceptions\UxonExceptionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;

/**
 * Exception thrown if the entity (widget, action, etc.) represented by a UXON description cannot be instantiated due to invalid or missing properties.
 * 
 * If the entity exists alread, it's class-specific exceptions (e.g. widget or action exceptions) should be preferred
 * to this general exception.
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonParserError extends RuntimeException implements UxonExceptionInterface {
	
	use ExceptionTrait {
		create_debug_widget as parent_create_debug_widget;
	}
	
	private $uxon = null;
	
	/**
	 * 
	 * @param UxonObject $uxon
	 * @param string $message
	 * @param string $alias
	 * @param \Throwable $previous
	 */
	public function __construct (UxonObject $uxon, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_uxon($uxon);
	}
	
	public function get_uxon(){
		return $this->uxon;
	}
	
	public function set_uxon(UxonObject $uxon){
		$this->uxon = $uxon;
		return $this;
	}
	
	public function create_debug_widget(DebugMessage $debug_widget){
		$debug_widget = $this->parent_create_debug_widget($debug_widget);
		if ($debug_widget->get_child('uxon_tab') === false){
			$page = $debug_widget->get_page();
			$uxon_tab = $debug_widget->create_tab();
			$uxon_tab->set_id('UXON');
			$uxon_tab->set_caption('UXON');
			$request_widget = WidgetFactory::create($page, 'Html');
			$uxon_tab->add_widget($request_widget);
			$request_widget->set_value('<pre>' . $this->get_uxon()->to_json(true) . '</pre>');
			$debug_widget->add_tab($uxon_tab);
		}
		return $debug_widget;
	}
}
?>