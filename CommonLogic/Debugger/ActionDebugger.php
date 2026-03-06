<?php
namespace exface\Core\CommonLogic\Debugger;

use exface\Core\CommonLogic\Tasks\CliTask;
use exface\Core\CommonLogic\Tasks\ScheduledTask;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Widgets\DebugMessage;

/**
 * Helps extract useful debug information from actions - in particular for debug widgets
 * 
 * @author Andrej Kabachnik
 *
 */
class ActionDebugger implements iCanGenerateDebugWidgets
{
    private ActionInterface $action;
    private ?TaskInterface $task;

    /**
     * @param ActionInterface $action
     * @param TaskInterface|null $task
     */
    public function __construct(ActionInterface $action, ?TaskInterface $task = null)
    {
        $this->action = $action;
        $this->task = $task;
    }

    /**
     * @return UiPageInterface|null
     */
    public function getPage() : ?UiPageInterface
    {
        $page = null;
        try {
            switch (true) {
                case $this->task !== null && $this->task->isTriggeredOnPage():
                    $page = $this->task->getPageTriggeredOn();
                    break;
                case $this->action->isDefinedInWidget():
                    $page = $this->action->getWidgetDefinedIn()->getPage();
                    break;
                default:
                    $page = null;
            }
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException(new ActionRuntimeError(
                $this->action, 'DEBUG error: cannot determine page of debugged action', null, $e)
            );
        }
        return $page;
    }

    /**
     * @return iTriggerAction|null
     */
    public function getTriggerWidget() : ?WidgetInterface
    {
        switch (true) {
            case $this->task !== null && $this->task->isTriggeredByWidget():
                $triggerWidget = $this->task->getWidgetTriggeredBy();
                break;
            case $this->action->isDefinedInWidget():
                $triggerWidget = $this->action->getWidgetDefinedIn();
                break;
            default:
                $triggerWidget = null;
        }
        return $triggerWidget;
    }

    /**
     * @return string
     */
    public function getTriggerName() : string
    {
        $triggerWidget = $this->getTriggerWidget();
        if ($triggerWidget !== null) {
            $triggerName = $triggerWidget->getCaption() ?? '';
            if ($triggerName === '') {
                $triggerName = $this->getAction()->getName();
            }
        } else {
            $triggerName = $this->getAction()->getName();
        }
        return $triggerName;
    }

    public function getTriggerUiPath() : string
    {
        $widget = $this->getTriggerWidget();
        $task = $this->getTask();
        switch (true) {
            case $widget:
                return WidgetDebugger::getWidgetUiPath($widget);
            case $task !== null && $task->isTriggeredOnPage():
                return 'Page "' . $task->getPageTriggeredOn()->getCaption() . '"';
            case $task !== null && $task instanceof ScheduledTask:
                return 'Scheduler ' . $task->getSchedulerUid();
            case $task !== null && $task instanceof CliTask:
                return 'CLI command ' . $task->getCliCommandName();
        }
        return $widget ? WidgetDebugger::getWidgetUiPath($widget) : 'Not triggered by widget';
    }
    
    public function getTriggerUiPathMarkdown(bool $startWithPage = true, bool $includeLastWidgetType = true) : string
    {
        $widget = $this->getTriggerWidget();
        $task = $this->getTask();
        switch (true) {
            case $widget:
                return WidgetDebugger::getWidgetUiPathMarkdown($widget, $startWithPage, $includeLastWidgetType);
            case $task !== null && $task->isTriggeredOnPage():
                return 'Page [' . $widget->getPage()->getName() . '](' . $widget->getPage()->getAliasWithNamespace() . '.html)';
        }
        return $this->getTriggerUiPath();
    }

    /**
     * @return WidgetInterface|null
     */
    public function getInputWidget() : ?WidgetInterface
    {
        $triggerWidget = $this->getTriggerWidget();
        if ($triggerWidget instanceof iUseInputWidget) {
            $inputWidget = $triggerWidget->getInputWidget();
        } else {
            $inputWidget = $triggerWidget;
        }
        return $inputWidget;
    }

    /**
     * @return string|null
     */
    public function getInputName() : ?string
    {
        $inputWidget = $this->getInputWidget();
        return $inputWidget?->getCaption();
    }
    
    public function getInputUiPath() : ?string
    {
        $inputWidget = $this->getInputWidget();
        return $inputWidget ? WidgetDebugger::getWidgetUiPath($inputWidget) : null;
    }
    
    public function getAction() : ActionInterface
    {
        return $this->action;
    }
    
    public function getTask() : ?TaskInterface
    {
        return $this->task;
    }
    
    public function getWorkbench()
    {
        return $this->action->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see iCanGenerateDebugWidgets::createDebugWidget
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        return $this->getAction()->createDebugWidget($debug_widget);
    }
}