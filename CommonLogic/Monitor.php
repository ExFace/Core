<?php
namespace exface\Core\CommonLogic;

use exface\Core\Actions\ReadData;
use exface\Core\Actions\UxonValidate;
use exface\Core\CommonLogic\Debugger\ActionDebugger;
use exface\Core\CommonLogic\Debugger\Profiler;
use exface\Core\CommonLogic\Debugger\WidgetDebugger;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Facades\ConsoleFacade;
use exface\Core\Interfaces\Actions\iExportData;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Events\Workbench\OnBeforeStopEvent;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Widgets\Button;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Actions\UxonAutosuggest;
use exface\Core\Facades\AbstractHttpFacade\Middleware\ContextBarApi;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Actions\ShowContextPopup;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Events\Workbench\OnCleanUpEvent;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Events\ActionEventInterface;
use exface\Core\Events\Action\OnActionFailedEvent;
use exface\Core\CommonLogic\Log\Handlers\MonitorLogHandler;

/**
 * The monitor logs actions to the MONITOR_ACTION object.
 * 
 * @author Andrej Kabachnik
 *
 */
class Monitor extends Profiler
{
    const CLEANUP_AREA_MONITOR = 'monitor';
    
    private $rowObjects = [];
    
    private ?ActionInterface $requestFirstAction = null;
    
    private $actionsEnabled = false;

    private $errorsEnabled = false;

    private bool $longRunnersEnabled = false;
    private int $longRunnersThresholdRead = -1;
    private int $longRunnersThreshold = -1;
    private string $longRunnersLogLevel = LoggerInterface::DEBUG;
    private $longRunningActionsHandler = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param float $startOffsetMs
     */
    public function __construct(WorkbenchInterface $workbench, float $startTimeMs = null)
    {
        parent::__construct($workbench, $startTimeMs);
    }

    /**
     *
     * @param WorkbenchInterface $workbench
     * @param float|null         $startTimeMs
     */
    public static function register(WorkbenchInterface $workbench, float $startTimeMs = null) : void 
    {
        $self = new self($workbench, $startTimeMs);        
        $config = $workbench->getConfig();

        // Do not monitor anything while installing the workbench
        if ($workbench->isInstalled() === false) {
            return;
        }
        
        $self->actionsEnabled = $config->getOption('MONITOR.ACTIONS.ENABLED');
        $self->errorsEnabled = $config->getOption('MONITOR.ERRORS.ENABLED');
        
        $self->longRunnersEnabled = $config->getOption('MONITOR.LONG_RUNNERS.ENABLED');
        if ($self->longRunnersEnabled === true) {
            if ($config->getOption('MONITOR.LONG_RUNNERS.EXCLUDE_CLI') === true && ConsoleFacade::isPhpScriptRunInCli()) {
                $self->longRunnersEnabled = false;
            } else {
                $self->longRunnersThresholdRead = $config->getOption('MONITOR.LONG_RUNNERS.THRESHOLD_SECONDS_FOR_READS');
                $self->longRunnersThreshold = $config->getOption('MONITOR.LONG_RUNNERS.THRESHOLD_SECONDS_FOR_OTHERS');
                $self->longRunnersLogLevel = $config->getOption('MONITOR.LONG_RUNNERS.LOG_LEVEL');
            }
        }
        
        $self->registerEventListeners();
        if ($self->errorsEnabled) {
            $self->registerLogHandler();
        }
    }
    
    /**
     * 
     * @return \exface\Core\CommonLogic\Monitor
     */
    protected function registerEventListeners()
    {
        $eventManager = $this->getWorkbench()->eventManager();
        
        if ($this->actionsEnabled || $this->longRunnersEnabled) {
            $eventManager->addListener(OnBeforeActionPerformedEvent::getEventName(), [
                $this,
                'onActionStart'
            ]);
        }
        // Actions
        if ($this->actionsEnabled || $this->longRunnersEnabled || $this->errorsEnabled) {            
            $eventManager->addListener(OnActionPerformedEvent::getEventName(), [
                $this,
                'onActionStop'
            ]);
            $eventManager->addListener(OnActionFailedEvent::getEventName(), [
                $this,
                'onActionStop'
            ]);
            $eventManager->addListener(OnBeforeStopEvent::getEventName(), [
                $this,
                'onWorkbenchStop'
            ]);
        }
        
        return $this;
    }
    
    /**
     * 
     * @return \exface\Core\CommonLogic\Monitor
     */
    protected function registerLogHandler()
    {
        $workbench = $this->getWorkbench();
        $config = $this->getWorkbench()->getConfig();
        if ($config->hasOption("MONITOR.ERRORS.MINIMUM_LEVEL_TO_LOG")) {
            $level = $config->getOption("MONITOR.ERRORS.MINIMUM_LEVEL_TO_LOG");
        } else {
            $level = LoggerInterface::CRITICAL;
        }
        $handler = new MonitorLogHandler($workbench, $this, $level);
        $workbench->getLogger()->appendHandler($handler);
        return $this;
    }
    
    /**
     *
     * @return void
     */
    public static function onCleanUp(OnCleanUpEvent $event)
    {
        if (! $event->isAreaToBeCleaned(self::CLEANUP_AREA_MONITOR)) {
            return;
        }
        $workbench = $event->getWorkbench();
        
        // Delete any errors that are older than allowed unless not in a final status yet.
        $ds = DataSheetFactory::createFromObjectIdOrAlias($workbench, 'exface.Core.MONITOR_ERROR');
        $ds->getFilters()->addConditionFromString('DATE', (-1)*$workbench->getConfig()->getOption('MONITOR.ERRORS.DAYS_TO_KEEP'), ComparatorDataType::LESS_THAN);
        $ds->getFilters()->addConditionFromString('STATUS', 90, ComparatorDataType::LESS_THAN);
        $errorsCnt = $ds->dataDelete();
        
        // Delete any actions that are older thant allowed unless associated with a pending error
        $ds = DataSheetFactory::createFromObjectIdOrAlias($workbench, 'exface.Core.MONITOR_ACTION');
        $ds->getFilters()->addConditionFromString('DATE', (-1)*max($workbench->getConfig()->getOption('MONITOR.ACTIONS.DAYS_TO_KEEP'), $workbench->getConfig()->getOption('MONITOR.ERRORS.DAYS_TO_KEEP')), ComparatorDataType::LESS_THAN);
        // FIXME this condition leads to a very strange SQL. Need fix!
        // $ds->getFilters()->addConditionFromString('MONITOR_ERROR__MONITOR_ACTION', EXF_LOGICAL_NULL, ComparatorDataType::EQUALS);
        $actionCnt = $ds->dataDelete();
        
        $event->addResultMessage('Cleaned up Monitor removing ' . $errorsCnt . ' expired errors and ' . $actionCnt . ' actions.');
    }
    
    /**
     * 
     * @param OnBeforeActionPerformedEvent $event
     * @return void
     */
    public function onActionStart(OnBeforeActionPerformedEvent $event) : void
    {
        $action = $event->getAction();
        // Make sure we know, what is the first action started, so we can use its alias/name for log
        // entries.
        if ($this->requestFirstAction === null) {
            $this->requestFirstAction = $action;
        }
        if ($this->isActionMeasured($action)) {
            $this->start($action);
        }
    }
    
    /**
     * 
     * @param OnActionPerformedEvent $event
     * @return void
     */
    public function onActionStop(ActionEventInterface $event) : void
    {
        $action = $event->getAction();
        
        $actionMs = 0;
        if ($this->isActionMeasured($action)) {
            $lap = $this->stop($action);
            $actionMs = $lap->getTimeTotalMs();
        }
        
        // Log long-running actions
        if ($this->longRunnersEnabled && $actionMs > 0) {
            $thresholdMs = 1000 * $action->getMonitorAsLongRunningAfterSeconds($this->getActionLongRunningThreshold($action));
            if ($thresholdMs > -1 && $actionMs > $thresholdMs) {
                $msg = 'Long-running action detected: ' . $action->__toString() . ' ran for ' . TimeDataType::formatMs($actionMs) . ' (> ' . TimeDataType::formatMs($thresholdMs) . ' threshold)!';
                $this->logLongRunningAction($msg, $action);
            }
        }

        // Log action to action monitor
        if ($this->actionsEnabled === true && $this->isActionMonitored($action)) {
            $this->addRowFromAction($action, $event->getTask(), $actionMs);
        }
    }
    
    protected function logLongRunningAction(string $msg, ?ActionInterface $action = null) : void
    {
        if ($action === null) {
            $exception = new RuntimeException($msg);
        } else {
            $exception = new ActionRuntimeError($action, $msg);
        }
        $exception->setLogLevel($this->longRunnersLogLevel);

        $this->getLongRunningActionsHandler()->handle(
            $this->longRunnersLogLevel,
            $msg,
            ['id' => $exception->getId()],
            $exception
        );
    }

    /**
     * @param ActionInterface $action
     * @return int
     */
    protected function getActionLongRunningThreshold(ActionInterface $action) : int
    {
        switch (true) {
            case $action instanceof ReadData && ! ($action instanceof iExportData):
                return $this->longRunnersThresholdRead;
            default:
                return $this->longRunnersThreshold;
        }
    }

    /**
     * Returns TRUE 
     * 
     * @param ActionInterface $action
     * @return bool
     */
    protected function isActionMeasured(ActionInterface $action) : bool
    {
            // If monitoring long runners is enabled AND the action is not explicitly excluded
            ($this->longRunnersEnabled && $action->getMonitorAsLongRunningAfterSeconds() !== -1)
            // OR monitoring actions is enabled generally and the action is to be monitored
            || ($this->actionsEnabled && $this->isActionMonitored($action));
    }
    
    /**
     * 
     * @param OnBeforeStopEvent $event
     * @return void
     */
    public function onWorkbenchStop(OnBeforeStopEvent $event)
    {
        // Log long-running requests
        if ($this->longRunnersEnabled === true) {
            $totalMs = $this->getTimeElapsedMs();
            $longRunnerMsg = null;
            try {
                if ($this->requestFirstAction !== null && $this->isActionMeasured($this->requestFirstAction)) {
                    $thresholdMs = 1000 * $this->getActionLongRunningThreshold($this->requestFirstAction);
                    if ($thresholdMs > -1 && $thresholdMs < $totalMs) {
                        $longRunnerMsg = $this->requestFirstAction->getAliasWithNamespace();
                        if ($this->requestFirstAction->hasMetaObject()) {
                            $longRunnerMsg .= ' on ' . $this->requestFirstAction->getMetaObject()->getAliasWithNamespace();
                        }
                    }
                } else {
                    $thresholdMs = 1000 * $this->longRunnersThreshold;
                    if ($thresholdMs > -1 && $thresholdMs < $totalMs) {
                        $requestId = $this->getWorkbench()->getContext()->getScopeRequest()->getRequestId();
                        $longRunnerMsg = 'request id ' . $requestId;
                    }
                }

                if ($longRunnerMsg !== null) {
                    $msg = 'Long running request detected: ' . $longRunnerMsg . ' ran for ' . TimeDataType::formatMs($totalMs) . ' (> ' . TimeDataType::formatMs($thresholdMs) . ' threshold)!';
                    $this->logLongRunningAction($msg, $this->requestFirstAction);
                }
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException(
                    new RuntimeException('Cannot log long-running request to monitor. ' . $e->getMessage(), null, $e)
                );
            }
        }
        
        // Save data
        if (! empty($this->rowObjects)) {
            try {
                foreach ($this->getWorkbench()->data()->getTransactions() as $tx) {
                    if ($tx->isOpen()) {
                        $tx->rollBack();
                    }
                }
                $this->saveData();
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
            }
        }        
    }
    
    public function addLogIdToLastRowObject(string $ids) : void
    {
        if (empty($this->rowObjects)) {
            return;
        }
        $idx = count($this->rowObjects) - 1;
        $this->rowObjects[$idx]['logIds'][] = $ids;
        return;
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @return bool
     */
    protected function isActionMonitored(ActionInterface $action) : bool
    {
        switch (true) {
            // Ignore ReadData actions - there are too many not monitor.
            case $action instanceof iReadData: 
            // Same goes for the following administration-related actions
            case $action instanceof UxonAutosuggest:
            case $action instanceof UxonValidate:
            case $action instanceof ContextBarApi:
            case $action instanceof ShowContextPopup:
                return false;
            default:
                return true;
        }
    }

    /**
     *
     * @param ActionInterface $action
     * @param TaskInterface   $task
     * @param float|null      $duration
     * @return Monitor
     */
    protected function addRowFromAction(ActionInterface $action, TaskInterface $task, float $duration = null) : Monitor
    {
        $this->rowObjects[] = [
            'type' => 'action',
            'action' => $action,
            'task' => $task,
            'duration' => $duration,
            'time' => DateTimeDataType::now(),
            'logIds' => []
        ];
        return $this;
    }
    
    /**
     * 
     * @return Monitor
     */
    protected function saveData() : Monitor
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.MONITOR_ACTION');
        foreach ($this->rowObjects as $item) {
            if (! $this->actionsEnabled && empty($item['logIds'])) {
                continue;
            }
            /* @var $action \exface\Core\Interfaces\Actions\ActionInterface */
            $action = $item['action'];
            /* @var $task \exface\Core\Interfaces\Tasks\TaskInterface */
            $task = $item['task'];
            
            if (! $action || ! $task) {
                continue;
            }
            
            $actionDebugger = new ActionDebugger($action, $task);
            $page = $actionDebugger->getPage();
            
            try {
                $object = $action->getMetaObject();
            } catch (\Throwable $e) {
                $object = null;
            }
            
            $ds->addRow([
                'PAGE' => $page ? $page->getUid() : null,
                'OBJECT' => $object ? $object->getId() : null,
                'ACTION_ALIAS' => $action->getAliasWithNamespace(),
                'ACTION_NAME' => $actionDebugger->getTriggerName(),
                'WIDGET_NAME' => $actionDebugger->getInputUiPath(),
                'FACADE_ALIAS' => $task->getFacade() ? $task->getFacade()->getAliasWithNamespace() : '',
                'USER' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
                'TIME' => $item['time'],
                'DATE' => DateDataType::cast($item['time']),
                'DURATION' => $this->getTimeTotalMs(),
                'TASK_CLASS' => PhpClassDataType::findClassNameWithoutNamespace($task),
                'REQUEST_SIZE' => $task instanceof HttpTaskInterface ? $task->getHttpRequest()->getHeader('Content-Length')[0] : null,
                'UI_FLAG' => $task->getFacade() instanceof AbstractAjaxFacade
            ]);
            
            $ds->dataCreate();
            
            $logIds = $item['logIds'];
            if (! empty($logIds)) {
                $actionUid = $ds->getUidColumn()->getValue(0);
                $errorDs = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.MONITOR_ERROR');
                $errorDs->getColumns()->addFromSystemAttributes();
                $errorDs->getColumns()->addFromExpression('ACTION');
                $uidAlias = $errorDs->getMetaObject()->getUidAttributeAlias();
                $errorDs->getFilters()->addConditionFromValueArray($uidAlias, $logIds);
                $errorDs->dataRead();
                $errorDs->getColumns()->getByExpression('ACTION')->setValueOnAllRows($actionUid);
                $errorDs->dataUpdate();              
            }
            $ds->removeRows();
        }
        
        /*if (! $ds->isEmpty()) {
            $ds->dataCreate();
        }*/ 
        
        return $this;
    }

    /**
     * Returns a log handler specifically configured to log long-running actions. The result is cached to speed up
     * repeated calls.
     * 
     * Use it's `handle()` method to log any long-running actions.
     * 
     * @return MonitorLogHandler
     */
    protected function getLongRunningActionsHandler() : MonitorLogHandler
    {
        if($this->longRunningActionsHandler === null) {
            $this->longRunningActionsHandler = new MonitorLogHandler(
                $this->getWorkbench(),
                $this,
                $this->longRunnersLogLevel
            );
        }

        return $this->longRunningActionsHandler;
    }

    /**
     * Returns a log handler specifically configured to log long-running actions. The result is cached to speed up
     * repeated calls.
     * 
     * Use it's `handle()` method to log any long-running actions.
     * 
     * @return MonitorLogHandler
     */
    protected function getLongRunningActionsHandler() : MonitorLogHandler
    {
        if($this->longRunningActionsHandler === null) {
            $this->longRunningActionsHandler = new MonitorLogHandler(
                $this->getWorkbench(),
                $this,
                $this->longRunnersLogLevel
            );
        }

        return $this->longRunningActionsHandler;
    }
}
?>