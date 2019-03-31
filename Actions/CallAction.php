<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iRunFacadeScript;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * This action performs another action specified in the action_alias property or via request parameter "call=your_action_alias".
 *
 * This action behaves exactly as the action to be called, but offers a universal interface for multiple action types. Thus, if you
 * need a custom server call somewhere in a facade, but you do not know, which action will be called in advance, you can request
 * this action an pass the actually desired one as a request parameter.
 *
 * @author Andrej Kabachnik
 *        
 */
class CallAction extends AbstractAction
{

    private $action = null;

    private $action_alias = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        return $this->getAction()->handle($task, $transaction);
    }

    /**
     *
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    public function getAction()
    {
        if (is_null($this->action)) {
            $action = ActionFactory::createFromString($this->getWorkbench(), $this->getActionAlias(), ($this->isDefinedInWidget() ? $this->getWidgetDefinedIn() : null));
            $this->validateAction($action);
            $this->action = $action;
        }
        return $this->action;
    }

    protected function validateAction(ActionInterface $action)
    {
        if ($action instanceof iRunFacadeScript) {
            throw new ActionInputError($this, 'Cannot call actions running facade scripts for object baskets! Attempted to call "' . $action->getAliasWithNamespace() . '".');
        }
        // Add other checks
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractAction::implementsInterface()
     */
    public function implementsInterface($string)
    {
        return $this->getAction()->implementsInterface($string);
    }

    public function isUndoable() : bool
    {
        // TODO make action wrapper undoable if wrapped action is undoable!
        return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractAction::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('action_alias', $this->getActionAlias());
        return $uxon;
    }

    /**
     *
     * @return string
     */
    public function getActionAlias()
    {
        if (is_null($this->action_alias)) {
            $this->action_alias = $this->getWorkbench()->getRequestParam('call');
        }
        return $this->action_alias;
    }

    /**
     *
     * @param string $value            
     * @return \exface\Core\Actions\ObjectBasketCallAction
     */
    public function setActionAlias($value)
    {
        $this->action_alias = $value;
        $this->action = null;
        return $this;
    }

    public function hasProperty($name)
    {
        if (parent::hasProperty($name)) {
            return true;
        } elseif ($this->getAction() && $this->getAction()->hasProperty($name)) {
            return true;
        }
        return false;
    }

    /**
     *
     * @param string $method            
     * @param array $arguments            
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        return call_user_func_array(array(
            $this->getAction(),
            $method
        ), $arguments);
    }

    public function setInputDataSheet($data_sheet_or_uxon)
    {
        return $this->getAction()->setInputDataSheet($data_sheet_or_uxon);
    }

    public function getInputDataSheet(TaskInterface $task) : DataSheetInterface
    {
        return $this->getAction()->getInputDataSheet($task);
    }

    public function getInputRowsMax()
    {
        return $this->getAction()->getInputRowsMax();
    }

    public function setInputRowsMax($value)
    {
        $this->getAction()->setInputRowsMax($value);
        return $this;
    }

    public function getInputRowsMin()
    {
        return $this->getAction()->getInputRowsMin();
    }

    public function setInputRowsMin($value)
    {
        $this->getAction()->setInputRowsMin($value);
        return $this;
    }
}