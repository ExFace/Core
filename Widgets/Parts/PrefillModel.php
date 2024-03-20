<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\Interfaces\Widgets\PrefillModelInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataPointerInterface;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Events\Widget\OnPrefillDataLoadedEvent;
use exface\Core\Events\Action\OnBeforeActionInputValidatedEvent;
use exface\Core\Events\Action\OnActionInputValidatedEvent;
use exface\Core\Interfaces\Tasks\ResultWidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\CommonLogic\Tasks\GenericTask;
use exface\Core\Interfaces\Actions\iPrefillWidget;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\Core\Actions\ShowWidget;

class PrefillModel implements PrefillModelInterface
{    
    private $bindings = [];
    
    private $widget = null;
    
    public function __construct(WidgetInterface $widget)
    {
        $this->widget = $widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\PrefillModelInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\PrefillModelInterface::addBindingsFromTask()
     */
    public function addBindingsFromTask(TaskInterface $task) : PrefillModelInterface
    {
        $this->emulatePrefill($task);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\PrefillModelInterface::addBindingsFromAction()
     */
    public function addBindingsFromAction(iPrefillWidget $action) : PrefillModelInterface
    {
        $prefillAction = $action->getPrefillAction();
        $prefillTask = new GenericTask($action->getWorkbench());
        $prefillTask->setActionSelector($prefillAction->getSelector());
        
        $this->emulatePrefill($prefillTask);
        return $this;
    }
    
    /**
     * Generates bindings by simulating a prefill for a widget by a given task
     *
     * Technically, the task is being modified in a way, that the prefill is performed. Then, the task is being
     * handled by the workbench and the resulting prefilled widget is returned.
     *
     * @param HttpTaskInterface $task
     *
     * @return WidgetInterface
     */
    protected function emulatePrefill(TaskInterface $task) : WidgetInterface
    {
        $widget = $this->getWidget();
        // The whole trick only makes sense for widgets, that are created by actions (e.g. via button press).
        // Otherwise we would not be able to find out, if the widget is supposed to be prefilled because
        // actions controll the prefill.
        // FIXME what about prefilling root widgets of pages? 
        if (! ($widget->getParent() instanceof iTriggerAction)) {
            return $widget;   
        }
        
        $button = $widget->getParent();
        // Make sure, the task has page and widget selectors (they are not set automatically, for routed URLs)
        $task->setPageSelector($button->getPage()->getSelector());
        $task->setWidgetIdTriggeredBy($button->getId());
        
        // Now see, what action, we are dealing with and whether it requires a prefill
        $action = $button->getAction();
        if ($action instanceof iCallOtherActions) {
            if (! $task->hasAction()) {
                $task->setActionSelector('exface.Core.ShowWidget');
            }
            $foundStep = $action->getActionToStart($task);
            if ($foundStep === null) {
                foreach ($action->getActions() as $step) {
                    if ($step instanceof ShowWidget) {
                        if ($foundStep !== null) {
                            throw new RuntimeException('Cannot emulate widget prefill for action chains with multiple ShowWidget actions!');
                        }
                        $foundStep = $step;
                    }
                }
            }
            if ($foundStep !== null) {
                $action = $foundStep;
            }
        }
        if (($action instanceof iShowWidget) && ($action->getPrefillWithInputData() || $action->getPrefillWithPrefillData() || $action->getPrefillWithFilterContext())) {
            // If a prefill is required, but there is no input data (e.g. a button with ShowObjectEditDialog was pressed and
            // the corresponding view or viewcontroller is being loaded), just fake the input data by reading the first row of
            // the default data for the input widget. Since we are just interested in model bindings, id does not matter, what
            // data we use as prefill - only it's structure matters!
            if (! $task->hasInputData() && $action->getPrefillWithInputData()) {
                try {
                    $task->setInputData($this->getExpectedInputData());
                } catch (\Throwable $e) {
                    throw new RuntimeException('Cannot determine prefill data. ' . $e->getMessage(), null, $e);
                }
            }
            if (! $task->hasPrefillData() && $action->getPrefillWithInputData() === false && $action->getPrefillWithPrefillData()) {
                try {
                    $task->setPrefillData($this->getExpectedPrefillData());
                } catch (\Throwable $e) {
                    throw new RuntimeException('Cannot determine prefill data. ' . $e->getMessage(), null, $e);
                }
            }
            
            // Listen to OnPrefillChangePropertyEvent and generate model bindings from it
            $prefillAppliedHandler = function($event) {
                $this->addBindingPointer($event->getWidget(), $event->getPropertyName(), $event->getPrefillValuePointer());
            };
            $widget->getWorkbench()->eventManager()->addListener(OnPrefillChangePropertyEvent::getEventName(), $prefillAppliedHandler);
            
            // Listen to the OnBeforePrefill event and empty data before prefilling the action's widget
            // to make sure there are no hard-coded values! This is important because we added a dummy
            // UID value above and also because filter contexts will add values directly to the sheet.
            $widget = $action->getWidget();
            $widget->getWorkbench()->eventManager()->addListener(OnPrefillDataLoadedEvent::getEventName(), function(OnPrefillDataLoadedEvent $event) use ($widget) {
                if ($event->getWidget() === $widget) {
                    $logBook = $event->getLogBook();
                    if ($logBook !== null) {
                        $logBook->addLine('Removing all values from the prefill data rows and filters to make sure there are no hard-coded values in the UI5 view. The real values will be loaded via `ReadPrefill` action later on.');
                    }
                    // Empty all values
                    foreach ($event->getDataSheet()->getColumns() as $col) {
                        $col->setValueOnAllRows('');
                    }
                    // Empty all filters
                    foreach ($event->getDataSheet()->getFilters()->getConditionsRecursive() as $cond) {
                        $cond->unsetValue();
                    }
                }
                return;
            });
                
            // Listen to OnBeforeActionInputValidated to disable any validators to ensure the
            // dummy data does not cause validations to fail.
            $widget->getWorkbench()->eventManager()->addListener(OnBeforeActionInputValidatedEvent::getEventName(), function(OnBeforeActionInputValidatedEvent $event) use ($action) {
                if ($event->getAction() !== $action) {
                    return;
                }
                $event->getAction()->getInputChecks()->setDisabled(true);
            });
                    
            // Listen to OnActionInputValidated to make sure, the input data of the action always
            // has dummy data - even if it was modified by input mappers or anything else.
            $widget->getWorkbench()->eventManager()->addListener(OnActionInputValidatedEvent::getEventName(), function(OnActionInputValidatedEvent $event) use ($action) {
                if ($event->getAction() !== $action) {
                    return;
                }
                $event->getAction()->getInputChecks()->setDisabled(false);
                $ds = $event->getDataSheet();
                if (! $ds->isEmpty() && $ds->hasUidColumn()) {
                    $this->generateDummyData($ds);
                }
            });
                        
        }
        // Overwrite the task's action with the action of the trigger widget to make sure, the prefill is really performed
        $task->setActionSelector($action->getSelector());
        
        // Handle the modified task
        try {
            $result = $widget->getWorkbench()->handle($task);
            if ($result instanceof ResultWidgetInterface) {
                $widget = $result->getWidget();
            }
        } catch (\Throwable $e) {
            // TODO
            throw $e;
        }
        
        return $widget;
    }
    
    protected function getExpectedInputWidget() : WidgetInterface
    {
        // TODO what if the button has has a custom `input_widget_id`?
        $widgetToPrefill = $this->getWidget();
        if ($widgetToPrefill->hasParent() && $widgetToPrefill->getParent() instanceof iUseInputWidget) {
            return $widgetToPrefill->getParent()->getInputWidget();
        }
        return null;
    }
    
    public function getExpectedInputData(WidgetInterface $inputWidget = null) : ?DataSheetInterface
    {
        $inputWidget = $inputWidget ?? $this->getExpectedInputWidget();
        if ($inputWidget === null) {
            return null;
        }
        $inputData = $inputWidget->prepareDataSheetToRead();
        $inputData = $this->generateDummyData($inputData);
        return $inputData;
    }
    
    public function getExpectedPrefillData(WidgetInterface $inputWidget = null) : ?DataSheetInterface
    {
        // TODO should the prefill data not be different? What about custom prefill widgets, etc.
        return $this->getExpectedInputData($inputWidget);
    }
    
    /**
     * Adds a dummy-row to the prefill data and makes sure it triggers the prefill logic.
     *
     * @param DataSheetInterface $dataSheet
     * @return DataSheetInterface
     */
    protected function generateDummyData(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        $row = [];
        
        // Make sure, there is a UID column - otherwise no prefill will take place!
        if ($dataSheet->hasUidColumn() === false && $dataSheet->getMetaObject()->hasUidAttribute() === true) {
            $dataSheet->getColumns()->addFromUidAttribute();
        }
        
        // Create empty values for every column and at least one row
        if ($dataSheet->isEmpty()) {
            foreach ($dataSheet->getColumns() as $col) {
                $row[$col->getName()] = '';
            }
            $dataSheet->addRow($row);
        } else {
            foreach ($dataSheet->getColumns() as $col) {
                $col->setValueOnAllRows('');
            }
        }
        
        // If the resulting sheet has a UID column, make sure it has a value - otherwise
        // the prefill logic will not attempt to ask the target widget for more columns
        // via prepareDataSheetToPrefill() because there would not be a way to read missing
        // values.
        if ($dataSheet->hasUidColumn(false) === true) {
            $dataSheet->getUidColumn()->setValue(0, 1);
        }
        
        // Make sure, that if a read operation is attempted for our dummy data, that will
        // not really take place! Otherwise our data will be removed if there are no rows
        // matching our dummy-UID.
        $dataSheet->getWorkbench()->eventManager()->addListener(OnBeforeReadDataEvent::getEventName(), function(OnBeforeReadDataEvent $event) use ($dataSheet) {
            $eventSheet = $event->getDataSheet();
            // Prevent read operations on our dummy-sheet as they will change or even remove our data!
            // If the prefill will cause a read operation, the prefill-sheet will be
            // a copy of our sheet, so we cant simply check if they are equal. However,
            // the prefill sheet will have our values and may have other columns with NULL
            // values.
            if ($eventSheet->getMetaObject()->isExactly($dataSheet->getMetaObject()) === true) {
                $row = $dataSheet->getRow(0);
                foreach ($eventSheet->getRow(0) as $fld => $val) {
                    if ($val !== null && $val !== $row[$fld]) {
                        return;
                    }
                }
                $event->preventRead();
            }
        });
            
        return $dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\PrefillModelInterface::addBindingPointer()
     */
    public function addBindingPointer(WidgetInterface $widget, string $bindingName, DataPointerInterface $pointer) : PrefillModelInterface
    {
        $this->bindings[$widget->getId()][$bindingName] = $pointer;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\PrefillModelInterface::getBinding()
     */
    public function getBinding(WidgetInterface $widget, string $bindingName) : ?DataPointerInterface
    {
        return ($this->bindings[$widget->getId()] ?? [])[$bindingName] ?? [];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\PrefillModelInterface::hasBinding()
     */
    public function hasBinding(WidgetInterface $widget, string $bindingName) : bool
    {
        return $this->bindings[$widget->getId()][$bindingName] !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\PrefillModelInterface::getBindings()
     */
    public function getBoundWidgetIds(): array
    {
        return array_keys($this->bindings);
    }
    
    public function getBindingsForWidgetId(string $widgetId) : array
    {
        return $this->bindings[$widgetId] ?? [];
    }
    
    public function getBindingsForWidget(WidgetInterface $widget) : array
    {
        return $this->getBindingsForWidgetId($widget->getId());
    }
    
    public function getBindings() : array
    {
        $array = [];
        foreach ($this->bindings as $widgetId => $bindings) {
            foreach ($bindings as $binding) {
                $array[] = $binding;
            }
        }
        return $array;
    }
}