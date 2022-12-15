<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Actions\Traits\iCallOtherActionsTrait;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Interfaces\Tasks\ResultDataInterface;

/**
 * This action performs another action selecting it dynamically based on the input.
 * 
 * For security reasons, all callable actions must be listed under `actions_allowed`.
 * 
 * The action to perform can be selected from this list via
 * - `action_selector_input_column` - that is, getting the action selector from a data column
 * - `actions_conditions` - a set of conditions for every action in `actions_allowed`. These
 * conditions will be evaluated against the input data. If a condition is met, the action with
 * the same index will be called.
 * 
 * **NOTE:** Many action properties, that are normally computed automatically, need to be set
 * manually here: the `name` of the action, its `effects`, etc. The reason is simply, that the
 * facades cannot know which action will be called when rendering the respecitve buttons/triggers. 
 * 
 * ## Examples
 * 
 * ### Call action matching certain input conditions
 * 
 * This action will open different editors depending on the model `ENTITY` in every input row.
 * The contents of the `input_mapper` is omitted for the sake of simplicity.
 * 
 * ```
 *  {
 *      "alias": "exface.Core.CallAction",
 *      "actions_allowed": [
 *          {
 *              "alias": "exface.Core.ShowObjectEditDialog",
 *              "object_alias": "exface.Core.OBJECT_BEHAVIORS",
 *              "input_mapper": {}
 *          },{
 *              "alias": "exface.Core.ShowObjectEditDialog",
 *              "object_alias": "exface.Core.OBJECT_ACTION",
 *              "input_mapper": {}
 *          }
 *      ],
 *      "actions_conditions":[
 *          {
 *              "operator":"AND",
 *              "conditions":[
 *                  {"value_left":"=~input!ENTITY","comparator":"==","value_right":"object_behavior"}
 *              ]
 *          },{
 *              "operator":"AND",
 *              "conditions":[
 *                  {"value_left":"=~input!ENTITY","comparator":"==","value_right":"object_action"}
 *              ]
 *          }
 *      ]
 *  }
 *  
 * ```
 * 
 * 
 * ### Call action specified in the data
 * 
 * This action expects the alias of the action to call to be found in the column `ACTION_ALIAS`
 * of the input data. If an input row contains `my.APP.Action1` as value in that column, Action1
 * will be called, etc.
 * 
 * This is simpler to configure, than `actions_conditions`, but it requires all actions to be
 * roughly of the same type - e.g. some back-end logic. You can't mix front-end, back-end, dialogs,
 * etc. here.
 * 
 * ```
 *  {
 *      "alias": "exface.Core.CallAction",
 *      "action_selector_input_column": "ACTION_ALIAS",
 *      "actions_allowed": [
 *          {"alias": "my.APP.Action1"},
 *          {"alias": "my.APP.Action2"}
 *      ]
 *  }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class CallAction extends AbstractAction implements iCallOtherActions
{
    use iCallOtherActionsTrait;
    
    const TASK_PARAM_ACTION_INDEX = 'start';
    
    private $actionInputColumnName = null;
    
    private $actionsAllowedUxon = null;
    
    private $actionsAllowed = [];
    
    private $actionConditionsUxon = null;
    
    private $actionConditions = [];

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $logbook = $this->getLogBook($task);
        if ($task->hasParameter(self::TASK_PARAM_ACTION_INDEX)) {
            $i = $task->getParameter(self::TASK_PARAM_ACTION_INDEX);
            $logbook->addLine('Task parameter `' . self::TASK_PARAM_ACTION_INDEX . '` found.');
            $action = $this->getActions()[$i];
            if ($action === null) {
                throw new ActionInputError($this, 'Invalid value "' . $i . '" for `start` in action "' . $this->getAliasWithNamespace() . '": no corresponding action found!');
            }
            $logbook->addLine('Performing action "' . $action->getAliasWithNamespace() . '".');
            $resultOfAction = $action->handle($task, $transaction);
            return $resultOfAction->withTask($task);
        }
        
        $inputSheet = $this->getInputDataSheet($task);
        $results = [];
        $lbId = $logbook->getId();
        $diagram .= 'graph TB' . PHP_EOL;
        foreach ($this->getActions() as $i => $action) {
            $actionSheet = $this->getActionData($inputSheet, $action);
            if (! $actionSheet->isEmpty(true)) {
                $diagram .= "{$lbId}T(Task) -->|{$actionSheet->countRows()}x {$actionSheet->getMetaObject()->getAlias()}| {$lbId}{$i}[{$action->getAliasWithNamespace()}]" . PHP_EOL;
                try {
                    $actionTask = $task->copy()->setInputData($actionSheet);
                    $results[$i] = $action->handle($actionTask, $transaction);
                } catch (\Throwable $e) {
                    $diagram .= "style {$lbId}{$i} {$logbook->getFlowDiagramStyleError()}" . PHP_EOL;
                    $logbook->setFlowDiagram($diagram);
                    throw $e;
                }
            } else {
                $diagram .= "{$lbId}T(Task) .-x|0| {$lbId}{$i}[{$action->getAliasWithNamespace()}]" . PHP_EOL;
            }
        }
        
        if (! empty($results)) {
            $firstIdx = array_key_first($results);
            $firstResult = $results[$firstIdx];
            
            $firstResultArrowComment = ($firstResult instanceof ResultDataInterface) ? "|{$firstResult->getData()->countRows()}x {$firstResult->getData()->getMetaObject()->getAlias()}|" : '';
            $diagram .= "{$lbId}{$firstIdx} -->{$firstResultArrowComment} {$lbId}R(Result)" . PHP_EOL;
            $logbook->setFlowDiagram($diagram);
            
            return $firstResult->withTask($task);
        }
        
        $logbook->setFlowDiagram($diagram);
        return ResultFactory::createMessageResult($task, 'No action to be performed!');
    }
    
    /**
     * 
     * @param DataSheetInterface $inputSheet
     * @param ActionInterface $action
     * @return DataSheetInterface
     */
    protected function getActionData(DataSheetInterface $inputSheet, ActionInterface $action) : DataSheetInterface
    {
        if (null !== ($colName = $this->getActionInputColumnName())) {
            $col = $inputSheet->getColumns()->get($colName);
            $sheet = $sheet = $inputSheet->copy()->removeRows();
            foreach ($col->getValues() as $rowIdx => $selector) {
                if ($selector !== null && $selector !== '' && $action->isExactly($selector)) {
                    $sheet->addRow($inputSheet->getRow($rowIdx));
                }
            }
        } else {
            $sheet = $inputSheet;
        }
        
        if (null !== $conditionGroup = $this->getActionFilter($action)) {
            $sheet = $sheet->extract($conditionGroup);
        }
        
        return $sheet;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::getActionToStart()
     */
    public function getActionToStart(TaskInterface $task) : ?ActionInterface
    {
        if ($task->hasParameter(self::TASK_PARAM_ACTION_INDEX)) {
            $i = $task->getParameter(self::TASK_PARAM_ACTION_INDEX);
            return $this->getActions()[$i];
        }
        
        $inputSheet = $this->getInputDataSheet($task);
        foreach ($this->getActions() as $action) {
            if ($this->getActionData($inputSheet, $action)->isEmpty(true) === false) {
                return $action;
            }
        }
        
        throw new ActionRuntimeError($this, 'Cannot call action dynamically in "' . $this->getAliasWithNamespace() . '" - no suitable action found in `actions_allowed`!');
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
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractAction::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::getActions()
     */
    public function getActions() : array
    {
        if (empty($this->actionsAllowed) && $this->actionsAllowedUxon !== null) {
            $trigger = $this->isDefinedInWidget() ? $this->getWidgetDefinedIn() : null;
            foreach ($this->actionsAllowedUxon->getPropertiesAll() as $uxon) {
                $this->actionsAllowed[] = ActionFactory::createFromUxon($this->getWorkbench(), $uxon, $trigger);
            }
        }
        return $this->actionsAllowed;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getActionInputColumnName() : ?string
    {
        return $this->actionInputColumnName;
    }
    
    /**
     * The data column name in the input data, that will contain the alias/selector of the action to call
     *
     * @uxon-property action_selector_input_column
     * @uxon-type metamodel:attribute|string
     *
     * @param string $value
     * @return CallAction
     */
    public function setActionSelectorInputColumn(string $value) : CallAction
    {
        $this->actionInputColumnName = $value;
        return $this;
    }
    
    /**
     * Array of action descriptions listing all actions that can be called dynamically
     * 
     * @uxon-property actions_allowed
     * @uxon-type \exface\Core\CommonLogic\AbstractAction[]
     * @uxon-template [{"alias": ""}]
     * 
     * @param UxonObject|ActionInterface[]|string[] $value
     * @return CallAction
     */
    public function setActionsAllowed($value) : CallAction
    {
        $this->actionsAllowed = [];
        $this->actionsAllowedUxon = null;
        switch (true) {
            case $value instanceof UxonObject:
                $this->actionsAllowedUxon = $value;
                break;
            case is_array($value):
                foreach ($value as $action) {
                    if ($action instanceof ActionInterface) {
                        $this->actionsAllowed[] = $action;
                    } else {
                        ($this->actionsAllowedUxon ?? new UxonObject())->append(new UxonObject(['alias' => $action]));
                    }
                }
                break;
            default: 
                throw new ActionConfigurationError($this, 'Invalid value for property `action_allowed`: expecting an array of actions or action UXONs');
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::getUseSingleTransaction()
     */
    public function getUseSingleTransaction(): bool
    {
        return true;
    }
    
    /**
     * 
     * @return ConditionalProperty[][]
     */
    public function getActionsConditions() : array
    {
        if (empty($this->actionConditions)) {
            if ($this->actionConditionsUxon === null) {
                return [];
            }
            
            foreach ($this->actionConditionsUxon as $uxon) {
                $this->actionConditions[] = new ConditionalProperty($this->getWidgetDefinedIn(), 'action_conditions', $uxon);
            }
        }
        
        return $this->actionConditions;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasActionsConditions() : bool
    {
        return $this->actionConditionsUxon !== null;
    }

    /**
     * 
     * @param ActionInterface $action
     * @return int|NULL
     */
    protected function getActionIndex(ActionInterface $action) : ?int
    {
        $i = array_search($action, $this->getActions());
        return $i === false ? null : $i;
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @return ConditionGroupInterface|NULL
     */
    protected function getActionFilter(ActionInterface $action) : ?ConditionalExpressionInterface
    {
        $i = $this->getActionIndex($action);
        $conditionalProp = $this->getActionsConditions()[$i];
        if ($conditionalProp !== null) {
            return ConditionGroupFactory::createFromConditionalProperty($conditionalProp->getConditionGroup(), $this->getMetaObject());
        }
        return null;
    }
    
    /**
     * Conditions for every action in `actions_allowed` - the first action will be called where the input matches the condition group
     *
     * E.g. 
     *
     * ```json
     *  {
     *      "alias": "CallAction",
     *      "actions_allowed": [
     *          {"alias": "exface.Core.ShowObjectEditDialog"},
     *          {"alias": "exface.Core.ShowObjectInfoDialog"}
     *      ],
     *      "actions_conditions" [
     *          {
     *              "operator": "AND", 
     *              "conditions": [
     *                  {"value_left": "STATUS", "comparator": "<=", "value_right": "20"}
     *              ]
     *          }, 
     *          {
     *              "operator": "AND", 
     *              "conditions": [
     *                  {"value_left": "STATUS", "comparator": ">", "value_right": "20"}
     *              ]
     *          }
     *      ]
     *  }
     *  
     * ```
     * 
     * @uxon-property actions_conditions
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalProperty[]
     * @uxon-template [{"operator": "AND", "conditions": [{"value_left": "", "comparator": "", "value_right": ""}]},{"operator": "AND", "conditions": [{"value_left": "", "comparator": "", "value_right": ""}]}]
     * @uxon-required true
     * 
     * @param UxonObject $value
     * @return CallAction
     */
    public function setActionsConditions(UxonObject $value) : CallAction
    {
        $this->actionConditions = [];
        $this->actionConditionsUxon = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getInputRowsMax()
     */
    public function getInputRowsMax()
    {
        return parent::getInputRowsMax() ?? $this->getActions()[0]->getInputRowsMax();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getInputRowsMin()
     */
    public function getInputRowsMin()
    {
        return parent::getInputRowsMin() ?? $this->getActions()[0]->getInputRowsMin();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getIcon()
     */
    public function getIcon()
    {
        return parent::getIcon() ?? $this->getActions()[0]->getIcon();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getName()
     */
    public function getName()
    {
        if (! parent::hasName() && $firstAction = $this->getActions()[0]) {
            return $firstAction->getName();
        }
        return parent::getName();
    }
}