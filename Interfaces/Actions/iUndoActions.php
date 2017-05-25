<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\Actions\ActionInterface;

interface iUndoActions
{

    /**
     * Returns an array of actions, that need to be undone
     *
     * @return ActionInterface[]
     */
    public function getActionsToUndo();
}