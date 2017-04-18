<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Exceptions\WarningExceptionInterface;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;

/**
 * This trait enables an exception to output more usefull specific debug information. It is used by all
 * ExFace-specific exceptions!
 *
 * @author Andrej Kabachnik
 *
 */
trait ExceptionTrait {
	
	use ImportUxonObjectTrait;
	
	private $alias = null;
	private $id = null;
	private $exception_widget = null;
	
	public function __construct ($message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
	}
	
	public function export_uxon_object(){
		return new UxonObject();
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
	 * Creates an ErrorMessage widget representing the exception.
	 * 
	 * Do not override this method in order to customize the ErrorMessage widget - implement create_debug_widget() instead.
	 * It is more convenient and does not require taking care of event handling, etc.
	 * 
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::create_widget()
	 * @final
	 * @param UiPageInterface $page
	 * @return ErrorMessage
	 */
	public function create_widget(UiPageInterface $page){
		// Make sure, the widget is generated only once. Otherwise different parts of the code might get different widgets (with different ids).
		if (!is_null($this->exception_widget)){
			return $this->exception_widget;
		}
		// Create a new error message
		/* @var $tabs \exface\Core\Widgets\ErrorMessage */
		$debug_widget = WidgetFactory::create($page, 'ErrorMessage');
		$debug_widget->set_meta_object($page->get_workbench()->model()->get_object('exface.Core.ERROR'));
		
		// Add a tab with a user-friendly error description
		$error_tab = $debug_widget->create_tab();
		$error_tab->set_caption($debug_widget->get_workbench()->get_core_app()->get_translator()->translate('ERROR.CAPTION'));
		if ($this->get_alias()){
			$error_ds = $this->get_error_data($page->get_workbench(), $this->get_alias());
			$error_heading = WidgetFactory::create($page, 'TextHeading', $error_tab)
				->set_heading_level(2)
				->set_value($debug_widget->get_workbench()->get_core_app()->get_translator()->translate('ERROR.CAPTION') . ' ' . $this->get_alias() . ': ' . $error_ds->get_cell_value('ERROR_TEXT', 0));
			$error_tab->add_widget($error_heading);
			$error_text = WidgetFactory::create($page, 'Text', $error_tab)
				->set_value($this->getMessage());
			$error_tab->add_widget($error_text);
			$error_descr = WidgetFactory::create($page, 'Text', $error_tab)
				->set_attribute_alias('DESCRIPTION');
			$error_tab->add_widget($error_descr);
			$error_tab->prefill($error_ds);
		} else {
			$error_heading = WidgetFactory::create($page, 'TextHeading', $error_tab)
			->set_heading_level(2)
			->set_value($this->getMessage());
			$error_tab->add_widget($error_heading);
		}
		$debug_widget->add_tab($error_tab);
		
		// Add a tab with the exception printout
		$stacktrace_tab = $debug_widget->create_tab();
		$stacktrace_tab->set_caption($debug_widget->get_workbench()->get_core_app()->get_translator()->translate('ERROR.STACKTRACE_CAPTION'));
		$stacktrace_widget = WidgetFactory::create($page, 'Html', $stacktrace_tab);
		$stacktrace_tab->add_widget($stacktrace_widget);
		$stacktrace_widget->set_value($page->get_workbench()->CMS()->sanitize_error_output($page->get_workbench()->get_debugger()->print_exception($this)));
		$debug_widget->add_tab($stacktrace_tab);
		
		// Add a tab with the request printout
		if ($page->get_workbench()->get_config()->get_option('DEBUG.SHOW_REQUEST_DUMP')){
			$request_tab = $debug_widget->create_tab();
			$request_tab->set_caption($page->get_workbench()->get_core_app()->get_translator()->translate('ERROR.REQUEST_CAPTION'));
			$request_widget = WidgetFactory::create($page, 'Html');
			$request_tab->add_widget($request_widget);
			$request_widget->set_value('<pre>' . $page->get_workbench()->get_debugger()->print_variable($_REQUEST) . '</pre>');
			$debug_widget->add_tab($request_tab);
		}
		
		// Add extra tabs from current exception
		$debug_widget = $this->create_debug_widget($debug_widget);
		
		// Recursively enrich the error widget with information from previous exceptions
		if ($prev = $this->getPrevious()){
			if ($prev instanceof ErrorExceptionInterface){
				$debug_widget = $prev->create_debug_widget($debug_widget);
			}
		}
		
		// Save the widget in case create_widget() is called again
		$this->exception_widget = $debug_widget;
		
		return $debug_widget;
	}
	
	protected function get_error_data(Workbench $exface, $error_code){
		
		$ds = DataSheetFactory::create_from_object_id_or_alias($exface, 'exface.Core.ERROR');
		$ds->get_columns()->add_from_expression('ERROR_TEXT');
		$ds->get_columns()->add_from_expression('DESCRIPTION');
		if ($error_code){
			$ds->add_filter_from_string('ERROR_CODE', $error_code);
			$ds->data_read();
		}
		return $ds;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::create_debug_widget()
	 */
	public function create_debug_widget(DebugMessage $debug_widget){
		return $debug_widget;
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
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::get_id()
	 */
	public function get_id(){
		if (is_null($this->id)){
			$this->id = uniqid('', true);
		}
		return $this->id;
	}
}
?>