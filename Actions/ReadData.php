<?php namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Exceptions\ActionRuntimeException;
use exface\Core\AbstractAction;

class ReadData extends AbstractAction implements iReadData {
	private $affected_rows = 0;
	private $update_filter_context = true;
	
	protected function perform(){
		$data_sheet = $this->get_input_data_sheet()->copy();
		$this->set_affected_rows($data_sheet->remove_rows()->data_read());
		
		// Replace the filter conditions in the current window context by the ones in this data sheet
		// It is important to do it after the data had been read, because otherwise the newly set
		// context filters would affect the result of the read operation (context filters are automatically 
		// applied to the query, each time, data is fetched)
		if ($this->get_update_filter_context()){
			$this->get_app()->exface()->context()->get_scope_window()->get_filter_context()->remove_all_conditions();
			foreach ($data_sheet->get_filters()->get_conditions() as $condition){
				$this->get_app()->exface()->context()->get_scope_window()->get_filter_context()->add_condition($condition);
			}
		}
		
		$this->set_result_data_sheet($data_sheet);
		$this->set_result_message($this->get_affected_rows() . ' entries read');
	}
	
	protected function get_affected_rows() {
		return $this->affected_rows;
	}
	
	protected function set_affected_rows($value) {
		if ($value == 0){
			$this->set_undoable(false);
		}
		$this->affected_rows = $value;
	}
	
	public function get_update_filter_context() {
		return $this->update_filter_context;
	}
	
	public function set_update_filter_context($value) {
		$this->update_filter_context = $value;
		return $this;
	}
	
	public function get_result_output(){
		if (!$this->get_called_by_widget()) throw new ActionRuntimeException('Security violaion! Cannot read data without a target widget in action "' . $this->get_alias_with_namespace() . '"!');
		$elem = $this->get_app()->exface()->ui()->get_template()->get_element($this->get_called_by_widget());
		$output = $elem->prepare_data($this->get_result_data_sheet());
		return $this->get_app()->exface()->ui()->get_template()->encode_data($output);
	} 
	  
}
?>