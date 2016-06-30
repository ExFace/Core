<?php
namespace exface\Core\Interfaces\Actions;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
interface iCanBeUndone {
	/**
	 * Performs the actual undo operation. One can say, it is the opposite of perform().
	 * In most cases undo() will use undo data from the context history.
	 * @return DataSheetInterface
	 */
	public function undo();
	
	/**
	 * Returns a serializable UXON object with all the data neede to perform an undo operation later.
	 * The output of this method will be saved in the context history.
	 * @return \stdClass
	 */
	public function get_undo_data_serializable();
	
	/**
	 * Imports the undo data from get_undo_data_serializable(), that is saved in the context history, back
	 * to an action instance. This method should work with whatever get_undo_data_serializable() returns for
	 * the same action. 
	 * @param \stdClass $uxon_object
	 */
	public function set_undo_data(\stdClass $uxon_object);
	
	/**
	 * @see ActionInterface::is_undoable()
	 */
	public function is_undoable();
	

	/**
	 * @return ActionInterface
	 */
	public function get_undo_action();
}