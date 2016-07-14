<?php namespace exface\Core\Actions;

use exface\Core\Exceptions\ActionRuntimeException;
use exface\Core\Interfaces\Actions\iUndoActions;
use exface\Core\CommonLogic\AbstractAction;

/**
 * This action performs an undo operation on one or more other actions from the action context history. 
 * @author Andrej Kabachnik
 *
 */
class UndoAction extends AbstractAction implements iUndoActions {
	private $undone_actions = 0;
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::init()
	 */
	function init(){
		$this->set_icon_name('undo');
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::perform()
	 */
	protected function perform(){
		foreach ($this->get_actions_to_undo() as $undo_action){
			if ($undo_action->is_undoable()){
				// IDEA To make the undo itself undoable, we need to instantiate and perform an undo action for every step. In this case,
				// we could again undo the undos in the same order.
				$result = $undo_action->undo();
				$this->undone_actions++;
			} else {
				throw new ActionRuntimeException('Cannot undo action "' . $undo_action->get_alias_with_namespace() . '". This type of action cannot be undone!');
			}
		}
		$this->set_result_data_sheet($result);
		$this->set_result_message('Successfully undone ' . $this->count_undone_actions() . ' actions!');
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\iUndoActions::get_actions_to_undo()
	 */
	public function get_actions_to_undo(){
		$data_sheet = $this->get_input_data_sheet();
		return $this->get_app()->get_workbench()->context()->get_scope_window()->get_action_context()->get_action_history($data_sheet && $data_sheet->count_rows() ? $data_sheet->count_rows() : 1);
	}
	
	public function count_undone_actions(){
		return $this->undone_actions;
	}
	
	/**
	 * Undoing actions does not produce any visible output
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_result_output()
	 */
	public function get_result_output(){
		return '';
	}
}
?>