<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iUndoActions;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;

/**
 * This action performs an undo operation on one or more other actions from the action context history.
 *
 * @author Andrej Kabachnik
 *        
 */
class UndoAction extends AbstractAction implements iUndoActions
{

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::init()
     */
    function init()
    {
        $this->setIcon(Icons::UNDO);
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transactions) : ResultInterface
    {
        $undone_actions = 0;
        foreach ($this->getActionsToUndo($task) as $undo_action) {
            if ($undo_action->isUndoable()) {
                // IDEA To make the undo itself undoable, we need to instantiate and perform an undo action for every step. In this case,
                // we could again undo the undos in the same order.
                $result_sheet = $undo_action->undo($transactions);
                $undone_actions ++;
            } else {
                throw new ActionUndoFailedError($this, 'Cannot undo action "' . $undo_action->getAliasWithNamespace() . '". This type of action cannot be undone!', '6T5DT14');
            }
        }
        
        $result = ResultFactory::createDataResult($task, $result_sheet);
        $result->setMessage($this->translate('RESULT', ['%number%' => $undone_actions], $undone_actions));
        
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\iUndoActions::getActionsToUndo()
     */
    protected function getActionsToUndo(TaskInterface $task)
    {
        $data_sheet = $this->getInputDataSheet($task);
        return $this->getApp()->getWorkbench()->getContext()->getScopeWindow()->getActionContext()->getActionHistory($data_sheet && $data_sheet->countRows() ? $data_sheet->countRows() : 1);
    }
}
?>