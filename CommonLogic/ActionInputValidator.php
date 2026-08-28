<?php

namespace exface\Core\CommonLogic;

use exface\Core\Exceptions\Actions\ActionTaskInvalidException;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Interfaces\Widgets\iInjectInputColumns;
use exface\Core\Interfaces\Widgets\iUseInputWidget;

/**
 * Wrapper class that contains various functions for validating action inputs. 
 * 
 * This class primarily exists to avoid unnecessary bloat in Actions, by limiting the availability of validation
 * functions to any context where an instance of this class is present.
 */
class ActionInputValidator
{
    protected ActionInterface $action;
    protected TaskInterface $task;

    /**
     * Creates a new instance and binds it to the given action and task. 
     * 
     * NOTE: Neither of these references can be changed. Create a new instance if you want to validate a different 
     * combination of task and action.
     * 
     * @param ActionInterface $action
     * @param TaskInterface   $task
     */
    function __construct(ActionInterface $action, TaskInterface $task)
    {
        $this->action = $action;
        $this->task = $task;
    }

    /**
     * Validates the task object and throws an error, should validation fail.
     * 
     * NOTE: If either the task or the action do not have an object, validation succeeds.
     * 
     * @return void
     * @throws ActionTaskInvalidException
     */
    public function validateTaskObject() : void
    {
        $action = $this->getAction();
        $task = $this->getTask();
        
        $taskObject = $task->hasMetaObject() ? $task->getMetaObject() : null;
        $actionObject = $action->hasMetaObject() ? $action->getMetaObject() : null;

        if($taskObject === null || $actionObject === null) {
            return;
        }

        // Ensure metaobjects match.
        if(!$taskObject->is($actionObject)) {

            // See if any input mapper has a matching from object.
            // If we can't match with an input mapper either, the task is invalid for this action.
            if($action->getInputMapper($taskObject) === null) {
                $taskAlias = $taskObject->getAliasWithNamespace();
                
                $error = new ActionTaskInvalidException(
                    $action,
                    $task,
                    'Action "' . $action->getAliasWithNamespace() . '" is defined for "' .
                    $actionObject->getAliasWithNamespace() . '", but received a task with object "' .
                    $taskAlias . '"!'
                );
                
                $error->setUseExceptionMessageAsTitle(true);
                $error->addIssue(ActionTaskInvalidException::ISSUE_INVALID_OBJECT, $taskAlias);
                $this->handleValidationError($error);
            }
        }
    }

    /**
     * Returns an array containing the names of columns that the action expects in its input data.
     * To explicitly allow certain columns, simply add them to the array.
     *
     * @param string               $widgetPrepareDataSheetFunction
     * You can specify what getter you wish to use, to retrieve a datasheet from the input widget. Make sure the 
     * function is actually supported by the input widget and returns an instance of `DataSheetInterface`.
     * @param WidgetInterface|null $inputWidget
     * Specify an input widget. If ´null´, the input widget will be determined automatically.
     * @return array
     */
    public function getExpectedColumns(
        string $widgetPrepareDataSheetFunction = 'prepareDataSheetToRead',
        WidgetInterface $inputWidget = null
    ) : array
    {
        $expectedColumns = [];
        
        $inputWidget = $inputWidget ?? $this->getInputWidget();
        if($inputWidget === null) {
            return $expectedColumns;
        }
        
        $this->addColumnsFromWidget(
            $expectedColumns,
            $inputWidget,
            $widgetPrepareDataSheetFunction
        );

        if($inputWidget instanceof iHaveConfigurator) {
            $this->addColumnsFromWidget(
                $expectedColumns,
                $inputWidget->getConfiguratorWidget(),
                'prepareDataSheetToRead'
            );
        }
        
        if($inputWidget instanceof iHaveColumns) {
            foreach ($inputWidget->getColumns() as $column) {
                $name = $column->getDataColumnName();
                $expectedColumns[$name] = $name;
            }
        }

        $this->addColumnsInjectedAtRuntime($expectedColumns);
        
        return $expectedColumns;
    }

    /**
     * Adds columns that the triggering widget declares as trusted (injected into the input at runtime).
     * 
     * Some actions add columns to their input data on the client side (e.g. the `dump_setup()` function
     * of a `DataTable` or a map drop-zone). The triggering button opts out of the forgery false positive
     * by listing these columns via `input_columns_trusted` - a server-side declaration that cannot be
     * forged from the client.
     * 
     * @param array $target
     * @return void
     */
    protected function addColumnsInjectedAtRuntime(array &$target) : void
    {
        $trigger = $this->getTriggerWidget();
        if(! $trigger instanceof iInjectInputColumns) {
            return;
        }
        foreach ($trigger->getInputColumnsTrusted() as $name) {
            $target[$name] = $name;
        }
    }

    /**
     * Deduces the input widget for the action.
     * 
     * @return WidgetInterface|null
     */
    protected function getInputWidget() : WidgetInterface|null
    {
        $widget = $this->getTriggerWidget();
        if($widget === null) {
            return null;
        }

        if($widget instanceof iUseInputWidget) {
            $widget = $widget->getInputWidget();
        }

        return $widget;
    }

    /**
     * Returns the widget that triggered the action (e.g. the button), before resolving its input widget.
     * 
     * @return WidgetInterface|null
     */
    protected function getTriggerWidget() : ?WidgetInterface
    {
        $task = $this->getTask();
        $action = $this->getAction();

        if($task->isTriggeredByWidget()) {
            return $task->getWidgetTriggeredBy();
        }
        if($action->isDefinedInWidget()) {
            return $action->getWidgetDefinedIn();
        }
        return null;
    }

    /**
     * Adds all columns from a given widget to 
     * 
     * @param array           $target
     * @param WidgetInterface $widget
     * @param string          $widgetPrepareDataSheetFunction
     * @return void
     */
    protected function addColumnsFromWidget(
        array &$target,
        WidgetInterface $widget,
        string $widgetPrepareDataSheetFunction
    ) : void
    {
        try {
            foreach ($widget->$widgetPrepareDataSheetFunction()->getColumns() as $column) {
                $name = $column->getName();
                $target[$name] = $name;
            }
        } catch (\Throwable $exception) {

        }
    }

    /**
     * Validates the task against a given list of columns. If any column in the task's input data is NOT among the
     * `$expectedColumns` an error will be thrown.
     * 
     * Validation succeeds if all input columns are expected or if the task doesn't have input data.
     * 
     * @param array $expectedColumns
     * @return void
     */
    public function validateTaskColumns(
        array $expectedColumns
    ) : void
    {
        if(empty($expectedColumns)) {
            return;
        }

        $action = $this->getAction();
        $task = $this->getTask();

        // Input synthesized server-side by a preceding action (e.g. within an action chain) cannot be
        // forged by the client, so it is exempt from the column forgery check.
        if($task->isInputDataTrusted()) {
            return;
        }
        
        // See if the task has input data.
        if(!$task->hasInputData()) {
            return;
        }

        $taskInput = $task->getInputData();
        $unexpectedColumns = [];

        // Now check if there are input columns that are not present in our expected structure, which would imply
        // a mistake or unauthorized attempts at accessing or manipulating data. Note that missing input columns
        // are of no concern here, since they might be handled later by mappers or prototype specific logic.
        foreach ($taskInput->getColumns() as $inputColumn) {
            // system attribute colums don't need to be checked, as they are added to a read without the widget knowing about them
            if ($inputColumn->isAttribute() && $inputColumn->getAttribute()->isSystem()) {
                continue;
            }            
            $inputColumnName = $inputColumn->getName();
            if(!key_exists($inputColumnName, $expectedColumns)) {
                $unexpectedColumns[$inputColumnName] = '"' . $inputColumnName . '"';
            }
        }

        if(!empty($unexpectedColumns)) {
            $message = 'Unexpected task input columns detected for action "' . $action->getAliasWithNamespace() .
                '": ' . implode(', ', $unexpectedColumns) . '!';
            // Only expose the remediation hint in developer mode - it could reveal an attack vector to end users.
            if ($action->getWorkbench()->getConfig()->getOption('DEBUG.PRETTIFY_ERRORS') === true) {
                $message .= ' If these columns are added to the input data at runtime (e.g. injected client-side by a ' .
                    'widget or a drop target), declare them on the triggering button via its ' .
                    '"input_columns_trusted" property so they are recognized as legitimate.';
            }
            $error = new ActionTaskInvalidException(
                $action,
                $task,
                $message
            );
            
            $error->setUseExceptionMessageAsTitle(true);
            foreach (array_keys($unexpectedColumns) as $unexpectedColumn) {
                $error->addIssue(ActionTaskInvalidException::ISSUE_UNEXPECTED_COLUMN, $unexpectedColumn);
            }
            $this->handleValidationError($error);
        }
    }
    
    protected function handleValidationError(ActionTaskInvalidException $error) : void
    {
        switch ($this->getValidationConfig()) {
            // Throw.
            case 'error':
                throw $error;
            // Log ERROR.
            case 'log':
                $this->action->getWorkbench()->getLogger()->logException($error);
                break;
            // Ignore.
            default:
                break;
        }
    }
    
    protected function getValidationConfig() : string
    {
        $cfgValue = $this->action->getWorkbench()->getConfig()->getOption('SECURITY.ACTION_INPUT.VALIDATION_ENABLED');
        return match ($cfgValue) {
            true => 'error',
            false => 'ignore',
            default => $cfgValue,
        };
    }

    /**
     * @return ActionInterface
     */
    public function getAction() : ActionInterface
    {
        return $this->action;
    }

    /**
     * @return TaskInterface
     */
    public function getTask() : TaskInterface
    {
        return $this->task;
    }
}