<?php namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iUpdateData;
use exface\Core\Interfaces\Actions\iCanBeUndone;
use exface\Core\Exceptions\ActionRuntimeException;

class UpdateData extends SaveData implements iUpdateData, iCanBeUndone {
	private $use_context_filters = false;
	
	protected function perform(){
		$data_sheet = $this->get_input_data_sheet();
		if (!$data_sheet->get_uid_column()){
			foreach ($this->get_app()->get_workbench()->context()->get_scope_window()->get_filter_context()->get_conditions($data_sheet->get_meta_object()) as $cond){
				$data_sheet->get_filters()->add_condition($cond);
			}
		}
		
		if ($this->get_use_context_filters()){
			if ($conditions = $this->get_workbench()->context()->get_scope_window()->get_filter_context()->get_conditions($data_sheet->get_meta_object())){
				foreach ($conditions as $condition){
					$data_sheet->get_filters()->add_condition($condition);
				}
			}
		}
		
		// Create a backup of the current data for this data sheet (it can be used for undo operations later)
		if ($data_sheet->count_rows() && $data_sheet->get_uid_column()){
			$backup = $data_sheet->copy();
			$backup->add_filter_from_column_values($backup->get_uid_column());
			$backup->remove_rows()->data_read();
			$this->set_undo_data_sheet($backup);
		} else {
			$this->set_undoable(false);
		}
		
		$this->set_affected_rows($data_sheet->data_update());
		$this->set_result('');
		$this->set_result_data_sheet($data_sheet);
		$this->set_result_message($this->get_app()->get_translator()->translate_plural('ACTION.UPDATEDATA.RESULT', $this->get_affected_rows(), array('%number%' => $this->get_affected_rows())));
	}
	
	public function undo(){
		if (!$data_sheet = $this->get_undo_data_sheet()){
			throw new ActionRuntimeException('Cannot undo action "' . $this->get_alias() . '": Failed to load history for this action!');
		}
		$data_sheet->data_update();
		return $data_sheet;
	}
	
	public function get_use_context_filters() {
		return $this->use_context_filters;
	}
	
	public function set_use_context_filters($value) {
		$this->use_context_filters = $value;
		return $this;
	}  
}
?>