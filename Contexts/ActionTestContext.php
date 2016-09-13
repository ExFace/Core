<?php namespace exface\Core\Contexts;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\ActionEvent;

/**
 * FIXME Use the generic DataContext instead of this ugly ActionTest specific context
 * @author Andrej Kabachnik
 *
 */
class ActionTestContext extends AbstractContext {
	private $recording = false;
	private $recording_test_case_id = null;
	private $recorded_steps_counter = 0;
	private $skip_next_actions = 0;
	private $skip_page_ids = array();
	
	public function recording_start(){
		$this->set_recorded_steps_counter(0);
		$this->recording = true;
		return $this;
	}
	
	public function recording_stop(){
		$this->recording = false;
		return $this;
	}
	
	public function is_recording(){
		return $this->recording;
	}
	
	/**
	 * Returns the number of upcoming actions to be skipped and not recorded.
	 * @return int
	 */
	public function get_skip_next_actions() {
		return $this->skip_next_actions;
	}
	
	/**
	 * Sets the number of upcoming actions to be skipped and not recorded.
	 * @param int $value
	 */
	public function set_skip_next_actions($number) {
		$this->skip_next_actions = $number;
		return $this;
	}  
	
	/**
	 * @return UxonObject
	 */
	public function export_uxon_object(){
		$uxon = $this->get_workbench()->create_uxon_object();
		if ($this->is_recording()){
			$uxon->recording = $this->is_recording();
			if ($this->get_skip_next_actions()){
				$uxon->skip_next_actions = $this->get_skip_next_actions();
			}
			if ($this->get_recorded_steps_counter()){
				$uxon->recorded_steps_counter = $this->get_recorded_steps_counter();
			}
			if ($this->get_recording_test_case_id()){
				$uxon->recording_test_case_id = $this->get_recording_test_case_id();
			}
			if ($this->get_skip_page_ids()){
				$uxon->skip_page_ids = $this->get_skip_page_ids();
			}
		}
		return $uxon;
	}
	
	/**
	 * @param UxonObject $uxon
	 * @throws UxonParserError
	 * @return ActionTestContext
	 */
	public function import_uxon_object(UxonObject $uxon){
		if (isset($uxon->recording)){
			$this->recording = $uxon->recording;
			
			// If we are recording, register a callback to record an actions output whenever an action is performed
			if ($this->is_recording()){
				$this->get_workbench()->event_manager()->add_listener('#.Action.Perform.After', array($this, 'record_action'));
				// Initialize the performance monitor
				$this->get_workbench()->get_app('exface.PerformanceMonitor');
			}
		}
		if (isset($uxon->skip_next_actions)){
			$this->set_skip_next_actions($uxon->skip_next_actions);
		}
		if (isset($uxon->recording_test_case_id)){
			$this->set_recording_test_case_id($uxon->recording_test_case_id);
		}
		if (isset($uxon->recording_test_case_id)){
			$this->set_recording_test_case_id($uxon->recording_test_case_id);
		}
		if (isset($uxon->recorded_steps_counter)){
			$this->set_recorded_steps_counter($uxon->recorded_steps_counter);
		}
		if (isset($uxon->skip_page_ids)){
			$this->set_skip_page_ids($uxon->skip_page_ids);
		}
		return $this;
	}
	
	public function record_action(ActionEvent $event){
		if ($this->get_skip_next_actions() > 0){
			$this->set_skip_next_actions($this->get_skip_next_actions()-1);
		} else {
			$action = $event->get_action();
				
			if ($action->get_called_by_widget()){
				$page_id = $action->get_called_by_widget()->get_page()->get_id();
			}
			if (is_null(page_id)) $page_id = $this->get_workbench()->cms()->get_page_id();
			
			// Only continue if the current page is not the excluded list
			//var_dump($page_id, $this->get_skip_page_ids());
			if (!in_array($page_id, $this->get_skip_page_ids())){
				// Create a test case if needed
				if (!$this->get_recording_test_case_id()){
					$test_case_data = $this->get_workbench()->data()->create_data_sheet($this->get_workbench()->model()->get_object('EXFACE.ACTIONTEST.TEST_CASE'));
					$test_case_data->set_cell_value('NAME', 0, $this->create_test_case_name($this->get_workbench()->cms()->get_page_name($page_id)));
					$test_case_data->set_cell_value('START_PAGE_ID', 0, $page_id);
					$test_case_data->set_cell_value('START_PAGE_NAME', 0, $this->get_workbench()->cms()->get_page_name($page_id));
					$test_case_data->set_cell_value('START_OBJECT', 0, $action->get_input_data_sheet()->get_meta_object()->get_id());
					$test_case_data->data_create();
					$this->set_recording_test_case_id($test_case_data->get_cell_value($test_case_data->get_meta_object()->get_uid_alias(), 0));
				}
				
				// Create the test step itself
				$data_sheet = $this->get_workbench()->data()->create_data_sheet($this->get_workbench()->model()->get_object('EXFACE.ACTIONTEST.TEST_STEP'));
				$data_sheet->set_cell_value('SEQUENCE', 0, ($this->get_recorded_steps_counter()+1));
				$data_sheet->set_cell_value('TEST_CASE', 0, $this->get_recording_test_case_id());
				$data_sheet->set_cell_value('ACTION_ALIAS', 0, $action->get_alias_with_namespace());
				$data_sheet->set_cell_value('ACTION_DATA', 0, $action->export_uxon_object()->to_json(true));
				$data_sheet->set_cell_value('OUTPUT_CORRECT', 0, $this->get_workbench()->get_app('exface.ActionTest')->prettify($action->get_result_output()));
				$data_sheet->set_cell_value('OUTPUT_CURRENT', 0, $this->get_workbench()->get_app('exface.ActionTest')->prettify($action->get_result_output()));
				$data_sheet->set_cell_value('MESSAGE_CORRECT', 0, $action->get_result_message());
				$data_sheet->set_cell_value('MESSAGE_CURRENT', 0, $action->get_result_message());
				$data_sheet->set_cell_value('RESULT_CORRECT', 0, $action->get_result_stringified());
				$data_sheet->set_cell_value('RESULT_CURRENT', 0, $action->get_result_stringified());
				if ($action->get_called_by_widget()){
					$data_sheet->set_cell_value('WIDGET_CAPTION', 0, $action->get_called_by_widget()->get_caption());
				}
				
				// Add performance monitor data
				/* @var $monitor_app \exface\PerformanceMonitor\PerformanceMonitorApp */
				if ($monitor_app = $this->get_workbench()->get_app('exface.PerformanceMonitor')){
					$duration = $monitor_app->get_monitor()->get_action_duration($action);
					$data_sheet->set_cell_value('DURATION_CORRECT', 0, $duration);
					$data_sheet->set_cell_value('DURATION_CURRENT', 0, $duration);
				}
				
				// Add page attributes
				$data_sheet->set_cell_value('PAGE_ID', 0, $page_id);
				$data_sheet->set_cell_value('PAGE_NAME', 0, $this->get_workbench()->cms()->get_page_name($page_id));
				$data_sheet->set_cell_value('OBJECT', 0, $action->get_input_data_sheet()->get_meta_object()->get_id());
				$data_sheet->set_cell_value('TEMPLATE_ALIAS', 0, $action->get_template_alias());
				
				// Save the step to the data source
				$data_sheet->data_create();
				$this->set_recorded_steps_counter($this->get_recorded_steps_counter()+1);
			}
		}
		return $this;
	}
	
	protected function create_test_case_name($page_name=null){
		return $page_name . ' (' . date($this->get_workbench()->get_config()->get_option('DEFAULT_DATETIME_FORMAT')) . ')';
	}
	
	public function get_recording_test_case_id() {
		return $this->recording_test_case_id;
	}
	
	public function set_recording_test_case_id($value) {
		$this->recording_test_case_id = $value;
		return $this;
	}
	
	public function get_recorded_steps_counter() {
		return $this->recorded_steps_counter;
	}
	
	public function set_recorded_steps_counter($value) {
		$this->recorded_steps_counter = $value;
		return $this;
	}    
	
	public function get_skip_page_ids() {
		return $this->skip_page_ids;
	}
	
	public function set_skip_page_ids(array $value) {
		$this->skip_page_ids = $value;
		return $this;
	}  
}
?>