<?php namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\AbstractBehavior;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Events\DataSheetEvent;
use exface\Core\Exceptions\MetaModelBehaviorException;
use exface\Core\Interfaces\Actions\iUndoActions;
use exface\Core\Exceptions\TimeStampingBehaviorError;

class TimeStampingBehavior extends AbstractBehavior {
	private $created_on_attribute_alias = null;
	private $updated_on_attribute_alias = null;
	private $check_for_conflicts_on_update = true;
	private $disabled = false;
	
	public function register(){
		$this->get_updated_on_attribute()->set_system(true);
		if ($this->get_check_for_conflicts_on_update()){
			$this->get_workbench()->event_manager()->add_listener($this->get_object()->get_alias_with_namespace() . '.DataSheet.UpdateData.Before', array($this, 'check_for_conflicts_on_update'));
		}
		$this->set_registered(true);
	}
	
	public function get_created_on_attribute_alias() {
		return $this->created_on_attribute_alias;
	}
	
	public function set_created_on_attribute_alias($value) {
		$this->created_on_attribute_alias = $value;
		return $this;
	}
	
	public function get_updated_on_attribute_alias() {
		return $this->updated_on_attribute_alias;
	}
	
	public function set_updated_on_attribute_alias($value) {
		$this->updated_on_attribute_alias = $value;
		return $this;
	}
	
	public function get_check_for_conflicts_on_update() {
		return $this->check_for_conflicts_on_update;
	}
	
	public function set_check_for_conflicts_on_update($value) {
		$this->check_for_conflicts_on_update = $value;
		return $this;
	}  
	
	/**
	 * @return Attribute
	 */
	public function get_created_on_attribute(){
		return $this->get_object()->get_attribute($this->get_created_on_attribute_alias());
	}
	
	/**
	 * @return Attribute
	 */
	public function get_updated_on_attribute(){
		return $this->get_object()->get_attribute($this->get_updated_on_attribute_alias());
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractBehavior::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = parent::export_uxon_object();
		$uxon->set_property('created_on_attribute_alias', $this->get_created_on_attribute_alias());
		$uxon->set_property('updated_on_attribute_alias', $this->get_updated_on_attribute_alias());
		$uxon->set_property('check_for_conflicts_on_update', $this->get_check_for_conflicts_on_update());
		return $uxon;
	}
	
	public function check_for_conflicts_on_update(DataSheetEvent $event){
		if ($this->is_disabled()) return;
		
		$data_sheet = $event->get_data_sheet();
		
		// Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
		// extended from it.
		if (!$data_sheet->get_meta_object()->is($this->get_object())){
			return;
		}
		
		// Check if the updated_on column is present in the sheet
		$updated_column = $data_sheet->get_columns()->get_by_attribute($this->get_updated_on_attribute());
		if (!$updated_column){
			throw new MetaModelBehaviorException('Cannot check for potential update conflicts in TimeStamping behavior: column "' . $this->get_updated_on_attribute_alias() . '" not found in given data sheet!');
		}
		
		$conflict_rows = array();
		// See, if the UndoAction is performed currently. It needs special treatment
		$current_action = $this->get_workbench()->context()->get_scope_window()->get_action_context()->get_current_action();
		if ($current_action instanceof iUndoActions){
			// FIXME To check for conflicts when performing and undo, we need to see, if the timestamp changed
			// since the undone action had been performed. The current problem is, however, that we do not store
			// the resulting data sheet of actions in the action history. So for now, undo will work without any
			// timestamp check. The probability of conflicts within the 3-5 seconds, when the undo link is displayed
			// is very small. Still, this really needs to be fixed!
		} else {
			// Check the current update timestamp in the data source
			$check_sheet = $data_sheet->copy()->remove_rows();
			$check_sheet->add_filter_from_column_values($data_sheet->get_uid_column());
			$check_sheet->data_read();
			$check_column = $check_sheet->get_columns()->get_by_attribute($this->get_updated_on_attribute());
			foreach ($updated_column->get_values() as $row_nr => $val){
				$check_val = $check_column->get_cell_value($check_sheet->get_uid_column()->find_row_by_value($data_sheet->get_uid_column()->get_cell_value($row_nr)));
				try {
					$val_date = new \DateTime($val);
					$check_date = new \DateTime($check_val);
				} catch (\Exception $e){
					$val_date = 0;
					$check_date = 0;
				}
				
				if ($val_date != $check_date){
					$conflict_rows[] = $row_nr;
				}
			}
		}
		
		if (count($conflict_rows) > 0){
			$data_sheet->data_mark_invalid();
			throw new TimeStampingBehaviorError('Cannot update data in data sheet with "' . $data_sheet->get_meta_object()->get_alias_with_namespace() . '": row(s) ' . implode(',', $conflict_rows) . ' changed by another user!');
		}
	}
}

?>