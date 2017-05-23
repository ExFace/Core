<?php namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Exceptions\DataSheets\DataSheetMergeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Actions\iUsePrefillData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\DataTypes\BooleanDataType;

/**
 * The ShowWidget action is the base for all actions, that render widgets. 
 * @author Andrej Kabachnik
 *
 */
class ShowWidget extends AbstractAction implements iShowWidget, iUsePrefillData {
	private $widget = null;
	private $widget_uxon = null;
	private $widget_id = null;
	private $prefill_with_filter_context = true;
	private $prefill_with_input_data = true;
	private $prefill_with_data_from_widget_link = null;
	/** @var DataSheetInterface $prefill_data_sheet */
	private $prefill_data_sheet = null;
	private $filter_contexts = array();
	private $page_id = null;
	
	protected function init(){
		$this->set_icon_name('link');
	}
		
	protected function perform(){
		$this->prefill_widget();
		if ($this->get_input_data_sheet()){
			$this->set_result_data_sheet($this->get_input_data_sheet());
		}
		$this->set_result($this->get_widget());
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\iShowWidget::get_widget()
	 */
	public function get_widget() {
		if (is_null($this->widget)){
			if ($this->get_widget_uxon()){
				$this->widget = WidgetFactory::create_from_uxon($this->get_called_on_ui_page(), $this->get_widget_uxon(), $this->get_called_by_widget());
			} elseif ($this->widget_id && !$this->page_id){
				$this->widget = $this->get_app()->get_workbench()->ui()->get_widget($this->widget_id, $this->get_called_on_ui_page()->get_id());
			} elseif ($this->page_id && !$this->widget_id){
				// TODO this causes problems with simple links to other pages, as the action attempts to load them here...
				//$this->widget = $this->get_app()->get_workbench()->ui()->get_page($this->page_id)->get_widget_root();
			} elseif ($this->page_id && $this->widget_id){
				$this->widget = $this->get_app()->get_workbench()->ui()->get_widget($this->widget_id, $this->page_id);
			}
		}
		return $this->widget;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\iShowWidget::set_widget()
	 */
	public function set_widget($widget_or_uxon_object) {
		if ($widget_or_uxon_object instanceof WidgetInterface){
			$widget = $widget_or_uxon_object;
		} elseif ($widget_or_uxon_object instanceof \stdClass){
			$this->set_widget_uxon($widget_or_uxon_object);
			$widget = null;
		} else {
			throw new ActionConfigurationError($this, 'Action "' . $this->get_alias() . '" expects the parameter "widget" to be either an instantiated widget or a valid UXON widget description object!', '6T91H2S');
		}
		$this->widget = $widget;
		return $this;
	}
	
	/**
	 * Prefills the widget of this action with any available data: the action's input data, prefill data from the request and filter contexts.
	 * 
	 * Technically, the method will attempt to create a data sheet from all those sources by merging them, read data for this sheet and perform
	 * a $this->get_widget()->prefill(). If the different sources for prefill data cannot be combined into a single data sheet, the widget will
	 * first get prefilled by input data and then by prefill data separately. If the context data cannot be combined with the input data, it will
	 * be ignored.
	 * 
	 * IDEA The idea of merging all into a single data sheet should save read operations. It makes things less transparent, however. Is it 
	 * worth it? Maybe do prefills sequentially instead starting with the leas significant data (context) and overwriting it with prefill data
	 * and input data subsequently?
	 * 
	 * @return void;
	 */
	protected function prefill_widget(){
		// Start with the prefill data already stored in the widget
		if ($this->get_widget()){
			$data_sheet = $this->get_widget()->get_prefill_data();
		}
		
		// Prefill with input data if not turned off
		if ($this->get_prefill_with_input_data() && $input_data = $this->get_input_data_sheet()){
			if (!$data_sheet || $data_sheet->is_empty()){
				$data_sheet = $input_data->copy();
			} else {
				try {
					$data_sheet = $data_sheet->merge($input_data);
				} catch (DataSheetMergeError $e){
					// If anything goes wrong, use the input data to prefill. It is more important for an action, than
					// other prefill sources.
					$data_sheet = $input_data->copy();
				}
			}
		}
		
		// Now prefill with prefill data.
		if ($prefill_data = $this->get_prefill_data_sheet()){
			// Try to merge prefill data and any data already gathered. If the merge does not work, ignore the prefill data
			// for now and use it for a secondary prefill later.
			$prefill_data_merge_failed = false;
			if (!$data_sheet || $data_sheet->is_empty()){
				$data_sheet = $prefill_data->copy();
			} else {
				try {
					$data_sheet = $data_sheet->merge($prefill_data);
				} catch (DataSheetMergeError $e){
					// Do not use the prefill data if it cannot be merged with the input data
					$prefill_data_merge_failed = true;
				}
			}
		}
		
		// See if the widget requires any other columns to be prefilled. If so, add them and check if data needs to be read.
		if ($data_sheet && $data_sheet->count_rows() > 0 && $data_sheet->get_uid_column()){
			$data_sheet = $this->get_widget()->prepare_data_sheet_to_prefill($data_sheet);
			if (!$data_sheet->is_fresh()){
				$data_sheet->add_filter_from_column_values($data_sheet->get_uid_column());
				$data_sheet->data_read();
			}
		}
		
		// Prefill widget using the filter contexts if the widget does not have any prefill data yet		
		// TODO Use the context prefill even if the widget already has other prefill data: use DataSheet::merge()!
		if ($this->get_prefill_with_filter_context() 
		&& $this->get_widget() 
		&& $context_conditions = $this->get_app()->get_workbench()->context()->get_scope_window()->get_filter_context()->get_conditions($this->get_widget()->get_meta_object())){
			if (!$data_sheet || $data_sheet->is_empty()){
				$data_sheet = DataSheetFactory::create_from_object($this->get_widget()->get_meta_object());
			}
			
			// Make sure, the context object fits the data sheet object.
			// TODO Currently we fetch context filters for the object of the action. If data sheet has another object, we ignore the context filters.
			// Wouldn't it be better to add the context filters to the data sheet or maybe even to the data sheet and the prefill data separately?
			if ($this->get_widget()->get_meta_object()->is($data_sheet->get_meta_object())){
				/* @var $condition \exface\Core\CommonLogic\Model\Condition */
				foreach($context_conditions as $condition){
					/*if ($this->get_widget() && $condition->get_expression()->get_meta_object()->get_id() == $this->get_widget()->get_meta_object_id()){
					 // If the expressions belong to the same object, as the one being displayed, use them as filters
					 // TODO Building the prefill sheet from context in different ways depending on the object of the top widget
					 // is somewhat ugly (shouldn't the children widgets get the chance, to decide themselves, what they do with the prefill)
					 $data_sheet->get_filters()->add_condition($condition);
					 } else*/
					if ($condition->get_comparator() == EXF_COMPARATOR_IS 
					|| $condition->get_comparator() == EXF_COMPARATOR_EQUALS 
					|| $condition->get_comparator() == EXF_COMPARATOR_IN){
						// If it is not the same object, as the one displayed, add the context values as filters
						try {
							$col = $data_sheet->get_columns()->add_from_expression($condition->get_expression());
							$col->set_values(array($condition->get_value()));
						} catch (\Exception $e){
							// Do nothing if anything goes wrong. After all the context prefills are just an attempt the help
							// the user. It's not a good Idea to throw a real error here!
						}
					}
				}
			}
			
		}
		
		if ($data_sheet){
			$this->get_widget()->prefill($data_sheet);
		}
		if ($prefill_data_merge_failed){
			$this->get_widget()->prefill($prefill_data);
		}
	}
	
	/**
	 * @return DataSheetInterface
	 */
	public function get_prefill_data_sheet(){
		return $this->prefill_data_sheet;
	}
	
	/**
	 * 
	 * @param DataSheetInterface|UxonObject $data_sheet_or_uxon_object
	 * @return ShowWidget
	 */
	public function set_prefill_data_sheet($data_sheet_or_uxon_object){
		$exface = $this->get_workbench();
		$data_sheet = DataSheetFactory::create_from_anything($exface, $data_sheet_or_uxon_object);
		if (!is_null($this->prefill_data_sheet)){
			try {
				$data_sheet = $this->prefill_data_sheet->merge($data_sheet);
				$this->prefill_data_sheet = $data_sheet;
			} catch (DataSheetMergeError $e){
				// Do nothing, if the sheets cannot be merged
			}
		} else {
			$this->prefill_data_sheet = $data_sheet;
		}
		return $this;
	}
	
	public function get_widget_id() {
		if ($this->get_widget()){
			return $this->get_widget()->get_id();
		} else {
			return $this->widget_id;
		}
	}
	
	public function set_widget_id($value) {
		$this->widget_id = $value;
	} 
	
	/**
	 * Returns FALSE, if the values of the currently registered context filters should be used to attempt to prefill the widget
	 * @return boolean
	 */
	public function get_prefill_with_filter_context() {
		return $this->prefill_with_filter_context;
	}
	
	/**
	 * If set to TRUE, the values of the filters registered in the window context scope will be used to prefill the widget (if possible)
	 * @param boolean $value
	 * @return \exface\Core\Actions\ShowWidget
	 */
	public function set_prefill_with_filter_context($value) {
		$this->prefill_with_filter_context = $value;
		return $this;
	}
	
	/**
	 * Returns TRUE, if the input data of the action should be used to prefill the widget shown, or FALSE otherwise
	 * @return boolean
	 */
	public function get_prefill_with_input_data() {
		return $this->prefill_with_input_data;
	}
	
	/**
	 * Set to TRUE, if the input data of the action should be used to prefill the widget shown, or FALSE otherwise.
	 * @param boolean $value
	 * @return ShowWidget
	 */
	public function set_prefill_with_input_data($value) {
		$this->prefill_with_input_data = $value;
		return $this;
	}  
	
	/**
	 * The output for actions showing a widget is the actual code for the template element representing that widget
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_result_output()
	 */
	public function get_result_output(){
		return $this->get_template()->draw($this->get_result());
	}
	
	/**
	 * ShowWidget needs some kind of widget representation in UXON in order to be recreatable from the UXON object.
	 * TODO Currently the widget is represented by widget_id and page_id and there is no action widget UXON saved here. This won't work for generated widgets!
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = parent::export_uxon_object();
		$uxon->widget_id = $this->get_widget_id();
		$uxon->page_id = $this->get_called_on_ui_page()->get_id();
		$uxon->prefill_with_filter_context = $this->get_prefill_with_filter_context();
		$uxon->prefill_with_input_data = $this->get_prefill_with_input_data();
		if ($this->get_prefill_data_sheet()){
			$uxon->set_property('prefill_data_sheet', $this->get_prefill_data_sheet()->export_uxon_object());
		}
		return $uxon;
	}	  
	
	public function get_page_id() {
		if ($this->get_widget()){
			return $this->get_widget()->get_page_id();
		}
		return $this->page_id;
	}
	
	public function set_page_id($value) {
		$this->page_id = $value;
		return $this;
	}
	
	/**
	 * @deprecated use set_page_id() instead! This method is kept for backwards compatibility only.
	 * @param string $value
	 * @return \exface\Core\Actions\ShowWidget
	 */
	public function set_document_id($value){
		return $this->set_page_id($value);
	}
	
	public function get_prefill_with_data_from_widget_link() {
		return $this->prefill_with_data_from_widget_link;
	}
	
	public function set_prefill_with_data_from_widget_link($string_or_widget_link) {
		$exface = $this->get_workbench();
		if ($string_or_widget_link){
			$this->prefill_with_data_from_widget_link = WidgetLinkFactory::create_from_anything($exface, $string_or_widget_link);
		}
		return $this;
	}
	
	protected function set_widget_uxon($uxon_object_or_string){
		$this->widget_uxon = UxonObject::from_anything($uxon_object_or_string);
		return $this;
	}
	
	protected function get_widget_uxon(){
		return $this->widget_uxon;
	}
	
	/**
	 * Disables the prefill for this action entirely.
	 * 
	 * @uxon-property do_not_prefill
	 * @uxon-type boolean
	 * 
	 * @return \exface\Core\Actions\ShowWidget
	 */
	public function set_do_not_prefill($value){
	    $value = BooleanDataType::parse($value) ? false : true;
	    $this->set_prefill_with_filter_context($value);
	    $this->set_prefill_with_input_data($value);
	    return $this;
	}
}
?>