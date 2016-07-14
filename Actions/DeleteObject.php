<?php namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iDeleteData;
use exface\Core\CommonLogic\AbstractAction;

class DeleteObject extends AbstractAction implements iDeleteData {
	private $affected_rows = 0;
	
	protected function init(){
		$this->set_input_rows_min(1);
		$this->set_input_rows_max(null);
		$this->set_icon_name('remove');
	}
	
	protected function perform(){
		/* @var $data_sheet \exface\Core\Interfaces\DataSheets\DataSheetInterface */
		$obj = $this->get_input_data_sheet()->get_meta_object();
		$ds = $this->get_app()->get_workbench()->data()->create_data_sheet($obj);
		$instances = array();
		foreach ($this->get_input_data_sheet()->get_rows() as $row){
			$instances[] = $row[$obj->get_uid_alias()];
		}

		if (count($instances) > 0){
			$ds->add_filter_in_from_string($obj->get_uid_alias(), $instances);
			$this->set_affected_rows($this->get_affected_rows() + $ds->data_delete());
		}
		$this->set_result_message($this->get_affected_rows() . ' entries deleted');
		// IDEA Currently the delete action returns an empty data sheet with a filter, but
		// no columns. Perhaps it is more elegant to return the input data sheet with a filter
		// and not data, but the columns still being there...
		$this->set_result_data_sheet($ds);
	}
	
	protected function get_affected_rows() {
		return $this->affected_rows;
	}
	
	protected function set_affected_rows($value) {
		$this->affected_rows = $value;
	}
	
	/**
	 * Deleting data does not produce any visible output
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_result_output()
	 */
	public function get_result_output(){
		return '';
	}
}
?>