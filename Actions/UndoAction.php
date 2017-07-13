<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iUndoActions;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * This action performs an undo operation on one or more other actions from the action context history.
 *
 * @author Andrej Kabachnik
 *        
 */
class UndoAction extends AbstractAction implements iUndoActions
{

    private $undone_actions = 0;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::init()
     */
    function init()
    {
        $this->setIconName(Icons::UNDO);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::perform()
     */
    protected function perform()
    {
        foreach ($this->getActionsToUndo() as $undo_action) {
            if ($undo_action->isUndoable()) {
                // IDEA To make the undo itself undoable, we need to instantiate and perform an undo action for every step. In this case,
                // we could again undo the undos in the same order.
                $result = $undo_action->undo($this->getTransaction());
                $this->undone_actions ++;
            } else {
                throw new ActionUndoFailedError($this, 'Cannot undo action "' . $undo_action->getAliasWithNamespace() . '". This type of action cannot be undone!', '6T5DT14');
            }
        }
        $this->setResult('');
        $this->setResultDataSheet($result);
        $this->setResultMessage($this->translate('RESULT', array(
            '%number%' => $this->countUndoneActions()
        ), $this->countUndoneActions()));
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\iUndoActions::getActionsToUndo()
     */
    public function getActionsToUndo()
    {
        $data_sheet = $this->getInputDataSheet();
        return $this->getApp()->getWorkbench()->context()->getScopeWindow()->getActionContext()->getActionHistory($data_sheet && $data_sheet->countRows() ? $data_sheet->countRows() : 1);
    }

    public function countUndoneActions()
    {
        return $this->undone_actions;
    }
}
?>