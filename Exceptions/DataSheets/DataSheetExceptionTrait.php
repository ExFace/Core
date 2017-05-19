<?php namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;

/**
 * This trait enables an exception to output data sheet specific debug information.
 *
 * @author Andrej Kabachnik
 *
 */
trait DataSheetExceptionTrait {
	
	use ExceptionTrait {
		create_debug_widget as parent_create_debug_widget;
	}
	
	private $data_sheet = null;
	
	public function __construct (DataSheetInterface $data_sheet, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_data_sheet($data_sheet);
	}
	
	/**
	 * 
	 * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
	 */
	public function get_data_sheet(){
		return $this->data_sheet;
	}
	
	/**
	 * 
	 * @param DataSheetInterface $sheet
	 * @return \exface\Core\Exceptions\DataSheets\DataSheetExceptionTrait
	 */
	public function set_data_sheet(DataSheetInterface $sheet){
		$this->data_sheet = $sheet;
		return $this;
	}
	
	public function create_debug_widget(DebugMessage $debug_widget){
		$debug_widget = $this->parent_create_debug_widget($debug_widget);
		$page = $debug_widget->get_page();
		// Add a tab with the data sheet UXON
		$uxon_tab = $debug_widget->create_tab();
		$uxon_tab->set_caption('DataSheet');
		$uxon_widget = WidgetFactory::create($page, 'Html');
		$uxon_tab->add_widget($uxon_widget);
		// Using symfony var-dumper causes enormous memory leaks for some reason
		// $uxon_widget->set_value($debug_widget->get_workbench()->get_debugger()->print_variable($this->get_data_sheet()->export_uxon_object()->to_array()));
		$uxon_widget->set_value('<pre>' . $this->get_data_sheet()->export_uxon_object()->to_json(true) . '</pre>');
		$debug_widget->add_tab($uxon_tab);
		return $debug_widget;
	}
	
}
?>