<?php
namespace exface\Apps\exface\Core\Actions;
use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Exceptions\ActionRuntimeException;
class CreateData extends SaveData implements iCreateData {
	
	protected function perform(){
		$data_sheet = $this->get_input_data_sheet()->copy();
		$this->set_affected_rows($data_sheet->data_create());
		$this->set_undo_data_sheet($data_sheet);
		$this->set_result_data_sheet($data_sheet);
		$this->set_result_message($this->get_affected_rows() . ' entries created');
	}
	
	public function undo(){
		if (!$data_sheet = $this->get_undo_data_sheet()){
			throw new ActionRuntimeException('Cannot undo action "' . $this->get_alias() . '": Failed to load history for this action!');
		}
		$data_sheet->data_delete();
		return $data_sheet;
	}
}
?>