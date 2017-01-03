<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Exceptions\WarningExceptionInterface;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\ErrorMessage;

trait ExceptionTrait {
	
	private $alias = null;
	
	public function __construct ($message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
	}
	
	public function export_uxon_object(){
		return new UxonObject();
	}
	
	public function import_uxon_object(UxonObject $uxon){
		foreach ($uxon as $property => $value){
			$method_name = 'set_' . $property;
			if (method_exists($this, $method_name)){
				call_user_func(array($this, $method_name), $value);
			} else {
				// Ignore invalid exception properties. They might originate from earlier versions of the export and should not bother us.
				// IDEA alternatively we can throw an exception here and catch it in those places, where we can accept wrong parameters.
			}
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::is_warning()
	 */
	public function is_warning(){
		if ($this instanceof WarningExceptionInterface){
			return true;
		}
		return false;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::is_error()
	 */
	public function is_error(){
		return $this->is_warning() ? false : true;
	}
	
	/**
	 * The basic error widget for an exception only contains one tab with the exception printout (stacktrace, etc.)
	 * 
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::create_widget()
	 * 
	 * @param UiPageInterface $page
	 * @return ErrorMessage
	 */
	public function create_widget(UiPageInterface $page){
		// Create a new error message
		/* @var $tabs \exface\Core\Widgets\ErrorMessage */
		$debug_message = WidgetFactory::create($page, 'ErrorMessage');
		$debug_message->set_meta_object($page->get_workbench()->model()->get_object('exface.Core.ERROR'));
		
		// Add a tab with the exception printout
		$error_tab = $debug_message->create_tab();
		$error_tab->set_caption($page->get_workbench()->get_core_app()->get_translator()->translate('ERROR.CAPTION'));
		$error_widget = WidgetFactory::create($page, 'Html');
		$error_tab->add_widget($error_widget);
		$error_widget->set_value($page->get_workbench()->get_debugger()->print_exception($this));
		$debug_message->add_tab($error_tab);
		
		return $debug_message;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::get_default_alias()
	 */
	public static function get_default_alias(){
		return '';
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::get_alias()
	 */
	public function get_alias(){
		return $this->alias;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::set_alias()
	 */
	public function set_alias($alias){
		$this->alias = $alias;
		return $this;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::get_status_code()
	 */
	public function get_status_code(){
		return 500;
	}
}
?>