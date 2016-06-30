<?php namespace exface\Apps\exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Interfaces\Actions\iCanBeUndone;
use exface\Core\Exceptions\ActionRuntimeException;
use exface\Core\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\AbstractAction;

class SaveData extends AbstractAction implements iModifyData, iCanBeUndone {
	private $affected_rows = 0;
	private $undo_data_sheet = null;
	
	function init(){
		$this->set_icon_name('save');
		$this->set_input_rows_min(0);
		$this->set_input_rows_max(null);
	}
	
	protected function perform(){
		$data_sheet = $this->get_input_data_sheet()->copy();
		$this->set_affected_rows($data_sheet->data_save());
		$this->set_result_data_sheet($data_sheet);
		$this->set_result_message($this->get_affected_rows() . ' entries created or updated');
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
	
	/**
	 *
	 * @return DataSheetInterface
	 */
	public function get_undo_data_sheet() {
		return $this->undo_data_sheet;
	}
	
	public function set_undo_data_sheet(DataSheetInterface $data_sheet) {
		$this->undo_data_sheet = $data_sheet;
	}
	
	public function get_undo_data_serializable(){
		if ($this->get_undo_data_sheet()){
			return $this->get_undo_data_sheet()->export_uxon_object();
		} else {
			return new UxonObject();
		}
	}
	
	public function set_undo_data(\stdClass $uxon_object){
		$exface = $this->get_app()->exface();
		$this->undo_data_sheet = DataSheetFactory::create_from_stdClass($exface, $uxon_object);
	}
	
	public function undo(){
		throw new ActionRuntimeException('Undo functionality not implemented yet for action "' . $this->get_alias() . '"!');
	}
	
	/**
	 * Saving data does not produce any visible output
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_result_output()
	 */
	public function get_result_output(){
		return '';
	}
}
?>