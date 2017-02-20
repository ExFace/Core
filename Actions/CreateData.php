<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;

class CreateData extends SaveData implements iCreateData {
	
	protected function perform(){
		$data_sheet = $this->get_input_data_sheet()->copy();
		$this->set_affected_rows($data_sheet->data_create(true, $this->get_transaction()));
		$this->set_undo_data_sheet($data_sheet);
		$this->set_result_data_sheet($data_sheet);
		$this->set_result('');
		$this->set_result_message($this->get_workbench()->get_core_app()->get_translator()->translate('ACTION.CREATEDATA.RESULT', array('%number%' => $this->get_affected_rows()), $this->get_affected_rows()));
	}
	
	public function undo(DataTransactionInterface $transaction = null){
		if (!$data_sheet = $this->get_undo_data_sheet()){
			throw new ActionUndoFailedError($this, 'Cannot undo action "' . $this->get_alias_with_namespace() . '": Failed to load history for this action!', '6T5DLGN');
		}
		$data_sheet->data_delete($transaction ? $transaction : $this->get_transaction());
		return $data_sheet;
	}
}
?>