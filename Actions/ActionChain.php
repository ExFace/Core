<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\CommonLogic\Model\ActionList;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\Actions\iRunFacadeScript;
use exface\Core\Interfaces\ActionListInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\CommonLogic\Tasks\ResultData;
use exface\Core\Interfaces\Actions\iCallOtherActions;

/**
 * This action chains other actions together and performs them one after another.
 *
 * All actions in the action-array will be performed one-after-another in the order of definition. Every action receives
 * the result data of it's predecessor as input. The first action will get the input from the chain action. The result
 * of the action chain is the result of the last action.
 * 
 * NOTE: actions showing widgets cannot be used in actions chains as the will not show anything!
 *
 * Here is a simple example for releasing an order and priniting it with a single button:
 * 
 * ```
 *  {
 *      "alias": "exface.Core.ActionChain",
 *      "name": "Release",
 *      "input_rows_min": 1,
 *      "input_rows_max": 1,
 *      "actions": [
 *          {
 *              "alias": "my.app.ReleaseOrder",
 *          },
 *          {
 *              "alias": "my.app.PrintOrder"
 *          }
 *      ]
 *  }
 * 
 * ```
 *
 * As a rule of thumb, the action chain will behave as the first action in the it: it will inherit it's name, trigger widget, input
 * restrictions, etc. Thus, in the above example, the action chain would inherit the name "Order" and `input_rows_min`=`0`. However, 
 * there are some important exceptions from this rule:
 * 
 * - the chain has modified data if at least one of the actions modified data
 *
 * By default, all actions in the chain will be performed in a single transaction. That is, all actions will get rolled back if at least
 * one failes. Set the property `use_single_transaction` to false to make every action in the chain run in it's own transaction.
 *
 * Action chains can be nested - an action in the chain can be another chain. This way, complex processes even with multiple transactions
 * can be modeled (e.g. the root chain could have `use_single_transaction` disabled, while nested chains would each have a wrapping transaction).
 *
 * @author Andrej Kabachnik
 *        
 */
class ActionChain extends AbstractAction implements iCallOtherActions
{

    private $actions = null;

    private $use_single_transaction = true;

    protected function init()
    {
        parent::init();
        $this->actions = new ActionList($this->getWorkbench(), $this);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        if ($this->getActions()->isEmpty()) {
            throw new ActionConfigurationError($this, 'An action chain must contain at least one action!', '6U5TRGK');
        }
        
        $data = $this->getInputDataSheet($task);
        $data_modified = false;
        $t = clone $task;
        foreach ($this->getActions() as $action) {
            // Prepare the action
            // All actions are all called by the widget, that called the chain
            if ($this->isDefinedInWidget()) {
                $action->setWidgetDefinedIn($this->getWidgetDefinedIn());
            }
            // If the chain should run in a single transaction, this transaction must be set for every action to run in
            $ts = $this->getUseSingleTransaction() ? $transaction : $this->getWorkbench()->data()->startTransaction();
            // Every action gets the data resulting from the previous action as input data
            $t->setInputData($data);
            
            // Perform
            $result = $action->handle($t, $ts);
            $message .= $result->getMessage() . "\n";
            if ($result->isDataModified()) {
                $data_modified = true;
            }
            if ($result instanceof ResultData) {
                $data = $result->getData();
            }
        }
        
        $result->setDataModified($data_modified);
        $result->setMessage($message);
        
        return $result;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::getActions()
     */
    public function getActions() : ActionListInterface
    {
        return $this->actions;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::setActions()
     */
    public function setActions($uxon_array_or_action_list) : iCallOtherActions
    {
        if ($uxon_array_or_action_list instanceof ActionListInterface) {
            $this->actions = $uxon_array_or_action_list;
        } elseif ($uxon_array_or_action_list instanceof UxonObject) {
            foreach ($uxon_array_or_action_list as $nr => $action_or_uxon) {
                if ($action_or_uxon instanceof UxonObject) {
                    $triggerWidget = $this->isDefinedInWidget() ? $this->getWidgetDefinedIn() : null;
                    $action = ActionFactory::createFromUxon($this->getWorkbench(), $action_or_uxon, $triggerWidget);
                } elseif ($action_or_uxon instanceof ActionInterface) {
                    $action = $action_or_uxon;
                } else {
                    throw new ActionConfigurationError($this, 'Invalid chain link of type "' . gettype($action_or_uxon) . '" in action chain on position ' . $nr . ': only actions or corresponding UXON objects can be used as!', '6U5TRGK');
                }
                $this->addAction($action);
            }
        } else {
            throw new WidgetPropertyInvalidValueError('Cannot set actions for ' . $this->getAliasWithNamespace() . ': invalid format ' . gettype($uxon_array_or_action_list) . ' given instead of and instantiated condition or its UXON description.');
        }
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::addAction()
     */
    public function addAction(ActionInterface $action) : iCallOtherActions
    {
        if ($action instanceof iShowWidget){
            throw new ActionConfigurationError($this, 'Actions showing widgets cannot be used within action chains!');
        }
        
        if ($action instanceof iRunFacadeScript){
            throw new ActionConfigurationError($this, 'Actions running facade scripts cannot be used within action chains!');
        }
        
        $this->getActions()->add($action);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::getUseSingleTransaction()
     */
    public function getUseSingleTransaction() : bool
    {
        return $this->use_single_transaction;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::setUseSingleTransaction()
     */
    public function setUseSingleTransaction(bool $value) : iCallOtherActions
    {
        $this->use_single_transaction = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getInputRowsMin()
     */
    public function getInputRowsMin()
    {
        return $this->getActions()->getFirst()->getInputRowsMin();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getInputRowsMax()
     */
    public function getInputRowsMax()
    {
        return $this->getActions()->getFirst()->getInputRowsMax();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::isUndoable()
     */
    public function isUndoable() : bool
    {
        return false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getName()
     */
    public function getName()
    {
        if (! parent::hasName() && empty($this->getActions()) === false) {
            return $this->getActions()->getFirst()->getName();
        }
        return parent::getName();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getIcon()
     */
    public function getIcon()
    {
        return parent::getIcon() ? parent::getIcon() : $this->getActions()->getFirst()->getIcon();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::implementsInterface()
     */
    public function implementsInterface($interface)
    {
        if ($this->getActions()->isEmpty()){
            return parent::implementsInterface($interface);
        }
        return $this->getActions()->getFirst()->implementsInterface($interface);
    }
    
    /**
     * For every method not exlicitly inherited from AbstractAciton attemt to call it on the first action.
     * 
     * @param mixed $method
     * @param mixed $arguments
     * @return mixed
     */
    public function __call($method, $arguments){
        return call_user_func_array(array($this->getActions()->getFirst(), $method), $arguments);
    }
}