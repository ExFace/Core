<?php
namespace exface\Core\Contexts;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ActionFactory;
use exface\Core\Exceptions\Contexts\ContextLoadError;
use exface\Core\CommonLogic\Contexts\AbstractContext;

class ActionContext extends AbstractContext
{

    private $action_history = array();

    private $action_history_raw = array();

    private $action_history_limit = 10;

    private $current_actions = array();

    /**
     * Returns the action being performed at this time.
     * That is the action, for which the context is not closed yet
     *
     * @return ActionInterface
     */
    public function getCurrentAction()
    {
        return $this->getActions()[count($this->getActions()) - 1];
    }

    /**
     * Returns an array with all actions, registered in this context during the current server request
     *
     * @return ActionInterface[]
     */
    public function getActions()
    {
        return $this->current_actions;
    }

    /**
     * Registers an action in this context
     *
     * @param ActionInterface $action            
     * @return \exface\Core\Contexts\ActionContext
     */
    public function addAction(ActionInterface $action)
    {
        $this->current_actions[] = $action;
        return $this;
    }

    /**
     * Returns a specified quantity of action contexts from the history starting from the most recent one.
     * The history holds all actions,
     * that modify data in the data source.
     * Returns the entire history, if $steps_back is not specified (=NULL)
     * IDEA Create a separate class for action history with methods to get the most recent item, etc. This would free the context scope from action specific methods
     *
     * @param integer $steps_back            
     * @return ActionInterface[]
     */
    public function getActionHistory($steps_back = null)
    {
        // If history not yet loaded, load it now
        if (count($this->action_history_raw) == 0) {
            $this->importUxonObject($this->getScope()->getSavedContexts($this->getAliasWithNamespace()));
        }
        
        // Put the last $steps_back actions from the history into an array starting with the most recent entry
        $result_raw = array();
        if ($steps_back > 0) {
            for ($i = 0; $i < $steps_back; $i ++) {
                if ($step = $this->action_history_raw[count($this->action_history_raw) - 1 - $i]) {
                    $result_raw[] = $step;
                }
            }
        } else {
            $result_raw = array_reverse($this->action_history_raw);
        }
        
        // Now instantiate actions for every entry of the array holding the required amount of history steps
        $result = array();
        foreach ($result_raw as $uxon) {
            $exface = $this->getWorkbench();
            $action = ActionFactory::createFromUxon($exface, $uxon->action);
            if ($uxon->undo_data) {
                $action->setUndoData($uxon->undo_data);
            }
            $result[] = $action;
        }
        
        // Return the array of actions
        return $result;
    }

    /**
     *
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->context()->getScopeSession();
    }

    /**
     *
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // First, grab the raw history
        $array = $this->action_history_raw;
        // ... and add the actions performed in the current request to the end of ist
        foreach ($this->getActions() as $action) {
            // Exclude actions, that do not modify data, such as navigation, template scripts, etc. (they are not historized)
            if (! $action->isDataModified())
                continue;
            // Otherwise create a new UXON object to hold the action itself and the undo data, if the action is undoable.
            $uxon = new UxonObject();
            $uxon->action = $action->exportUxonObject();
            if ($action->isUndoable()) {
                $uxon->undo_data = $action->getUndoDataSerializable();
            }
            $array[] = $uxon;
        }
        
        // Make sure, the array is not bigger, than the limit
        if (count($array) > $this->action_history_limit) {
            $array = array_slice($array, count($array) - $this->action_history_limit);
        }
        
        // Pack into a uxon object
        $uxon = $this->getWorkbench()->createUxonObject();
        if (count($array) > 0) {
            $uxon->action_history = $array;
        }
        return $uxon;
    }

    /**
     *
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        if (is_array($uxon->action_history)) {
            $this->action_history_raw = $uxon->action_history;
        } elseif (! is_null($uxon->action_history)) {
            throw new ContextLoadError($this, 'Cannot load action contexts: expecting UXON objects, received ' . gettype($uxon->action_history) . ' instead!');
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIcon()
     */
    public function getIcon()
    {
        return 'gear';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getName()
     */
    public function getName()
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.ACTION.NAME');
    }
}
?>