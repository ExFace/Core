<?php
namespace exface\Core\CommonLogic;

use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;
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
    
    private $actionsEnabled = false;
    
    private $errorsEnabled = false;
    
    /**
     * 
     * @param Workbench $workbench
     * @param int $startOffsetMs
     */
    public function __construct(Workbench $workbench, int $startOffsetMs = 0)
    {
        parent::__construct($workbench, $startOffsetMs);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public static function register(WorkbenchInterface $workbench) 
    {
        $self = new self($workbench);        
        $config = $workbench->getConfig();
        $self->actionsEnabled = $config->getOption('MONITOR.ACTIONS.ENABLED');
        $self->errorsEnabled = $config->getOption('MONITOR.ERRORS.ENABLED');
        
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
        
        if ($this->actionsEnabled) {
            $eventManager->addListener(OnBeforeActionPerformedEvent::getEventName(), [
                $this,
                'onActionStart'
            ]);
        }
        // Actions
        if ($this->actionsEnabled || $this->errorsEnabled) {            
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
        
        // Delete any actions that are older thant allowed unless associated with a pending error
        $ds = DataSheetFactory::createFromObjectIdOrAlias($workbench, 'exface.Core.MONITOR_ACTION');
        $ds->getFilters()->addConditionFromString('DATE', (-1)*$workbench->getConfig()->getOption('MONITOR.ACTIONS.DAYS_TO_KEEP'), ComparatorDataType::LESS_THAN);
        $ds->getFilters()->addNestedGroupFromString(EXF_LOGICAL_OR)
            ->addConditionFromString('MONITOR_ERROR', 'NULL', ComparatorDataType::EQUALS)
            ->addConditionFromString('MONITOR_ERROR__STATUS', 90, ComparatorDataType::LESS_THAN);
        $cnt = $ds->dataDelete();
        
        // Delete any errors that are older than allowed unless not in a final status yet.
        $ds = DataSheetFactory::createFromObjectIdOrAlias($workbench, 'exface.Core.MONITOR_ERROR');
        $ds->getFilters()->addConditionFromString('DATE', (-1)*$workbench->getConfig()->getOption('MONITOR.ERRORS.DAYS_TO_KEEP'), ComparatorDataType::LESS_THAN);
        $ds->getFilters()->addConditionFromString('STATUS', 90, ComparatorDataType::LESS_THAN);
        
        $cnt += $ds->dataDelete();
        
        $event->addResultMessage('Cleaned up Monitor removing ' . $cnt . ' expired entries.');
    }
    
    /**
     * 
     * @param OnBeforeActionPerformedEvent $event
     * @return void
     */
    public function onActionStart(OnBeforeActionPerformedEvent $event)
    {
        if (! $this->isActionMonitored($event->getAction())) {
            return;
        }
        
        $this->start($event->getAction(), 'Action "' . $event->getAction()->getAliasWithNamespace() . '"');
    }
    
    /**
     * 
     * @param OnActionPerformedEvent $event
     * @return void
     */
    public function onActionStop(ActionEventInterface $event)
    {
        if (! $this->isActionMonitored($event->getAction())) {
            return;
        }
        
        $ms = null;
        if ($this->actionsEnabled) {
            $ms = $this->stop($event->getAction());
        }        
        $this->addRowFromAction($event->getAction(), $event->getTask(), $ms);
        return;
    }
    
    /**
     * 
     * @param OnBeforeStopEvent $event
     * @return void
     */
    public function onWorkbenchStop(OnBeforeStopEvent $event)
    {
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
            case $action instanceof iReadData:
            case $action instanceof UxonAutosuggest:
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
     * @param TaskInterface $task
     * @param float $duration
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
            
            try {
                switch (true) {
                    case $task->isTriggeredOnPage():
                        $page = $task->getPageTriggeredOn();
                        break;
                    case $action->isDefinedInWidget():
                        $page = $action->getWidgetDefinedIn()->getPage();
                        break;
                    default:
                        $page = null;
                }
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
            }
            
            $triggerWidget = ($task->isTriggeredByWidget() ? $task->getWidgetTriggeredBy() : ($action->isDefinedInWidget() ? $action->getWidgetDefinedIn() : null));
            if ($triggerWidget instanceof iUseInputWidget) {
                $inputWidget = $triggerWidget->getInputWidget();
            } else {
                $inputWidget = $triggerWidget;
            }
            
            if ($triggerWidget) {
                $triggerName = $triggerWidget->getCaption() ?? '';
                if ($triggerName === '') {
                    $triggerName = $action->getName();
                }
            } else {
                $triggerName = $action->getName();
            }
            
            $inputName = $inputWidget ? $this->getInputName($inputWidget) : '';
            
            try {
                $object = $action->getMetaObject();
            } catch (\Throwable $e) {
                $object = null;
            }
            
            $ds->addRow([
                'PAGE' => $page ? $page->getUid() : null,
                'OBJECT' => $object ? $object->getId() : null,
                'ACTION_ALIAS' => $action->getAliasWithNamespace(),
                'ACTION_NAME' => $triggerName,
                'WIDGET_NAME' => $inputName,
                'FACADE_ALIAS' => $task->getFacade() ? $task->getFacade()->getAliasWithNamespace() : '',
                'USER' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
                'TIME' => $item['time'],
                'DATE' => DateDataType::cast($item['time']),
                'DURATION' => $this->getDurationTotal()
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
     * 
     * @param WidgetInterface $inputWidget
     * @return string
     */
    protected function getInputName(WidgetInterface $inputWidget) : string
    {
        $inputName = $inputWidget->getCaption();
        switch (true) {
            case $inputWidget instanceof Dialog && $inputWidget->hasParent():
                $btn = $inputWidget->getParent();
                if ($btn instanceof Button) {
                    if ($btnCaption = $btn->getCaption()) {
                        $inputName = $btnCaption;
                    }
                    $btnInput = $btn->getInputWidget();
                    $inputName = $this->getInputName($btnInput) . ' > ' . $inputName;
                }
                break;
        }
        return $inputName ?? $inputWidget->getWidgetType();
    }
}
?>