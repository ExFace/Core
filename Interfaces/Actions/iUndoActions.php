<?php namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\Actions\ActionInterface;

interface iUndoActions {
	/**
	 * Returns an array of actions, that need to be undone
	 * @return ActionInterface[]
	 */
	public function get_actions_to_undo(); 
}