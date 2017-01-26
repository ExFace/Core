<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;

class CreateData extends SaveData implements iCreateData {
	
	protected function perform(){
		$data_sheet = $this->get_input_data_sheet()->copy();
		$this->set_affected_rows($data_sheet->data_create());
		$this->set_undo_data_sheet($data_sheet);
		$this->set_result_data_sheet($data_sheet);
		$this->set_result('');
		$this->set_result_message($this->get_app()->get_translator()->translate_plural('ACTION.CREATEDATA.RESULT', $this->get_affected_rows(), array('%number%' => $this->get_affected_rows())));
	}
	
	public function undo(){
		if (!$data_sheet = $this->get_undo_data_sheet()){
			throw new ActionUndoFailedError($this, 'Cannot undo action "' . $this->get_alias_with_namespace() . '": Failed to load history for this action!', '6T5DLGN');
		}
		$data_sheet->data_delete();
		return $data_sheet;
	}
}
?>