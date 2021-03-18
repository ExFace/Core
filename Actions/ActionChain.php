<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\ActionFactory;
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
 * All actions in the action-array will be performed one-after-another in the order of definition. 
 * Every action receives the result data of it's predecessor as input. The first action will get 
 * the input from the chain action. The result of the action chain is the result of the last action.
 * 
 * **NOTE:** actions showing widgets cannot be used in actions chains as the will not show anything!
 * 
 * As a rule of thumb, the action chain will behave as the first action in the it: it will inherit it's 
 * name, trigger widget, input restrictions, etc. Thus, in the above example, the action chain would 
 * inherit the name "Order" and `input_rows_min`=`0`. However, there are some important exceptions 
 * from this rule:
 * 
 * - the chain has modified data if at least one of the actions modified data
 *
 * By default, all actions in the chain will be performed in a single transaction. That is, all actions 
 * will get rolled back if at least one failes. Set the property `use_single_transaction` to false 
 * to make every action in the chain run in it's own transaction.
 *
 * Action chains can be nested - an action in the chain can be another chain. This way, complex processes 
 * even with multiple transactions can be modeled (e.g. the root chain could have `use_single_transaction` 
 * disabled, while nested chains would each have a wrapping transaction).
 * 
 * ## Examples
 * 
 * ### Simple chain
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
 * ### Primary action with additional operations
 * 
 * In this example, the first action is what the user actually intends to do. However, this implies, 
 * that other actions need to be done (in this case, changing the `STATUS` attribute to `95`).
 * 
 * ```
 *  {
 *      "alias": "exface.Core.ActionChain",
 *      "name": "Retry",
 *      "input_rows_min": 1,
 *      "use_result_of_action": 0,
 *      "use_input_data_of_action": 0,
 *      "actions": [
 *          {
 *              "alias": "exface.Core.CopyData",
 *          },
 *          {
 *              "alias": "exface.Core.UpdateData",
 *              "inherit_columns_only_for_system_attributes": true,
 *              "column_to_column_mappings": [
 *                  {
 *                      "from": 95,
 *                      "to": "STATUS"
 *                  }
 *              ]
 *          }
 *      ]
 *  }
 * 
 * ```
 * 
 * ### Complex nested chain
 * 
 * In this example, the first aciton of the chain is another (inner) chain:
 * 
 * 1. The first action of the inner chain copies some object
 * 2. The second action of the inner chain sets the status of the original
 * object (it modifies the original and not the copied object because
 * `use_input_data_of_action` of the chain is set to `0`, so all actions
 * get the same input data as the action with index 0)
 * 3. The inner chain passes the result of it's first action - the copied
 * object - to the second action of the outer chain. The result of the inner
 * chain is controlled by `use_result_of_action` forcing the chain to yield
 * the result of the action with index 0.
 * 4. The second action of the outer chain does further processing of the
 * copied object.
 * 
 * All of this is happening in a single transaction (as long as the data source
 * supports transactions, of course). Thus, if anything goes wrong in any
 * of the chained actions, everything will get rolled back.
 * 
 * ```
 * {
 *   "alias": "exface.Core.ActionChain",
 *   "actions": [
 *     {
 *       "alias": "exface.Core.ActionChain",
 *       "use_result_of_action": 0,
 *       "use_input_data_of_action": 0,
 *       "actions": [
 *         {
 *           "alias": "exface.Core.CopyData"
 *         },
 *         {
 *           "alias": "exface.Core.UpdateData",
 *           "input_mapper": {
 *             "inherit_columns_only_for_system_attributes": true,
 *             "column_to_column_mappings": [
 *               {
 *                 "from": 95,
 *                 "to": "STATUS"
 *               }
 *             ]
 *           }
 *         }
 *       ]
 *     },
 *     {
 *       "alias": "my.App.FurtherProcessing"
 *     }
 *   ]
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *        
 */
class ActionChain extends AbstractAction implements iCallOtherActions
{

    private $actions = [];

    private $use_single_transaction = true;
    
    private $use_result_from_action_index = null;
    
    private $freeze_input_data_at_action_index = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        if (empty($this->getActions())) {
            throw new ActionConfigurationError($this, 'An action chain must contain at least one action!', '6U5TRGK');
        }
        
        $data = $this->getInputDataSheet($task);
        $chainResult = null;
        $chainResultIdx = $this->getUseResultOfAction() ?? (count($this->getActions()) - 1);
        $freezeInputIdx = $this->getUseInputDataOfAction();
        $data_modified = false;
        $t = clone $task;
        foreach ($this->getActions() as $idx => $action) {
            // Prepare the action
            
            // All actions are all called by the widget, that called the chain
            if ($this->isDefinedInWidget()) {
                $action->setWidgetDefinedIn($this->getWidgetDefinedIn());
            }
            
            // If the chain should run in a single transaction, this transaction must 
            // be set for every action to run in. Autocommit must be disabled for
            // every action too!
            if ($this->getUseSingleTransaction()) {
                $tx = $transaction;
                $action->setAutocommit(false);
            } else {
                $tx = $this->getWorkbench()->data()->startTransaction();
            }
            
            // Let the action handle a copy of the task
            
            // Every action gets the data resulting from the previous action as input data
            $t->setInputData($data);
            
            $result = $action->handle($t, $tx);
            $message .= $result->getMessage() . "\n";
            if ($result->isDataModified()) {
                $data_modified = true;
            }
            if (($freezeInputIdx === null || $freezeInputIdx > $idx) && $result instanceof ResultData) {
                $data = $result->getData();
            }
            if ($chainResultIdx === $idx) {
                $chainResult = $result;
            }
        }
        
        $chainResult = $chainResult ?? $result;
        
        $chainResult->setDataModified($data_modified);
        $chainResult->setMessage($this->getResultMessageText() ?? $message);
        
        return $chainResult;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::getActions()
     */
    public function getActions() : array
    {
        return $this->actions;
    }
    
    /**
     * 
     * @return ActionInterface
     */
    public function getActionFirst() : ActionInterface
    {
        return $this->getActions()[0];
    }

    /**
     * Array of action UXON descriptions for every action in the chain.
     * 
     * @uxon-property actions
     * @uxon-type \exface\Core\CommonLogic\AbstractAction[]
     * @uxon-template [{"alias": ""}]
     * 
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
        
        $this->actions[] = $action;
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
     * Set to FALSE to enclose each action in a separate transaction.
     * 
     * By default the entire chain is enclosed in a transaction, so no changes
     * are made to the data sources if at least one action fails.
     * 
     * **NOTE:** not all data sources support transactions! Non-transactional
     * sources will keep eventual changes even if the action chain is rolled
     * back!
     * 
     * @uxon-property use_single_transaction
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::setUseSingleTransaction()
     */
    public function setUseSingleTransaction(bool $value) : iCallOtherActions
    {
        $this->use_single_transaction = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getInputRowsMin()
     */
    public function getInputRowsMin()
    {
        return $this->getActionFirst()->getInputRowsMin();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getInputRowsMax()
     */
    public function getInputRowsMax()
    {
        return $this->getActionFirst()->getInputRowsMax();
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
            return $this->getActionFirst()->getName();
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
        return parent::getIcon() ? parent::getIcon() : $this->getActionFirst()->getIcon();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::implementsInterface()
     */
    public function implementsInterface($interface)
    {
        if (empty($this->getActions())){
            return parent::implementsInterface($interface);
        }
        return $this->getActionFirst()->implementsInterface($interface);
    }
    
    /**
     * For every method not exlicitly inherited from AbstractAciton attemt to call it on the first action.
     * 
     * @param mixed $method
     * @param mixed $arguments
     * @return mixed
     */
    public function __call($method, $arguments){
        return call_user_func_array(array($this->getActionFirst(), $method), $arguments);
    }
    
    /**
     * 
     * @return int|NULL
     */
    public function getUseResultOfAction() : ?int
    {
        return $this->use_result_from_action_index;
    }
    
    /**
     * Index of the inner action (starting with 0), that should provide the result of the chain.
     * 
     * By default, the result of the chain is the result of the last action in the
     * chain. Using this property, you can make the chain yield the result of any
     * other action. 
     * 
     * Use `0` for the first action in the chain, `1` for the second, etc. All subsequent 
     * actions will still get performed normally, but the result of this specified action
     * will be returned by the chain at the end.
     * 
     * This option can be used in combination with `use_input_data_of_action` to add
     * "secondary" actions, that perform additional operations on the same data as the
     * main action and do not affect the result.
     * 
     * @uxon-property use_result_of_action
     * @uxon-type integer
     * @uxon-template 0
     * 
     * @param int $value
     * @return ActionChain
     */
    public function setUseResultOfAction(int $value) : ActionChain
    {
        $this->use_result_from_action_index = $value;
        return $this;
    }
    
    /**
     * 
     * @return int|NULL
     */
    public function getUseInputDataOfAction() : ?int
    {
        return $this->freeze_input_data_at_action_index;
    }
    
    /**
     * Make all actions after the given index (starting with 0) use the same input data.
     * 
     * By default, each action uses the result data of the previous action as it's input.
     * Setting this option will force all actions after the given index to use the same
     * input data as the action at that index: e.g. 
     * - `0` means all actions will use the input data of the first action, 
     * - `1` means the second action will use the result of the first one as input, but
     * all following actions will use the same data as input.
     * 
     * This option can be used in combination with `use_result_of_action` to add
     * "secondary" actions, that perform additional operations on the same data as the
     * main action and do not affect the result.
     * 
     * @uxon-property use_input_data_of_action
     * @uxon-type integer
     * @uxon-template 0
     * 
     * @param int $value
     * @return ActionChain
     */
    public function setUseInputDataOfAction(int $value) : ActionChain
    {
        $this->freeze_input_data_at_action_index = $value;
        return $this;
    }
}