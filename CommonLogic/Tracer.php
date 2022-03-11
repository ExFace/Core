<?php
namespace exface\Core\CommonLogic;

use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Events\DataConnection\OnBeforeQueryEvent;
use exface\Core\Events\DataConnection\OnQueryEvent;
use exface\Core\Interfaces\Events\ActionEventInterface;
use exface\Core\CommonLogic\Log\Handlers\BufferingHandler;
use exface\Core\CommonLogic\Log\Handlers\LogfileHandler;
use exface\Core\CommonLogic\Log\Handlers\DebugMessageFileHandler;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\CommonLogic\Log\Handlers\MonologCsvFileHandler;
use exface\Core\Events\Workbench\OnBeforeStopEvent;
use exface\Core\Events\DataConnection\OnBeforeConnectEvent;
use exface\Core\Events\DataConnection\OnConnectEvent;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\Workbench\OnStartEvent;
use exface\Core\Events\Security\OnAuthenticatedEvent;
use exface\Core\Events\Model\OnMetaObjectLoadedEvent;
use exface\Core\Interfaces\Events\DataQueryEventInterface;
use exface\Core\Events\Contexts\OnContextInitEvent;
use exface\Core\Interfaces\Events\ContextEventInterface;

/**
 * The tracer dumps detailed logs to a special trace file, readable by the standard log viewer.
 * 
 * The tracer creates debug log messages for the following events:
 * 
 * - Actions: before/after perform
 * - Data sources: after each query
 * 
 * The tracer can be temporarily disabled using `disable()` and `enable()`.
 * 
 * @author Andrej Kabachnik
 *
 */
class Tracer extends Profiler
{
    
    private $log_handlers = [];
    
    private $disabled = false;
    
    private $dataQueriesTotalMS = 0;
    
    private $dataQueriesCnt = 0;
    
    private $conncetionsCnt = 0;
    
    private $connectionsTotalMS = 0;
    
    /**
     * 
     * @param Workbench $workbench
     * @param int $startOffsetMs
     */
    public function __construct(Workbench $workbench, int $startOffsetMs = 0)
    {
        parent::__construct($workbench, $startOffsetMs);
        $this->registerLogHandlers();
        $this->registerEventHandlers();
    }
    
    /**
     * 
     * @return Tracer
     */
    public function disable() : Tracer
    {
        $this->disabled = true;
        foreach ($this->log_handlers as $handler){
            $handler->setDisabled(true);
        }
        return $this;
    }
    
    /**
     * 
     * @return Tracer
     */
    public function enable() : Tracer
    {
        $this->disabled = false;
        foreach ($this->log_handlers as $handler){
            $handler->setDisabled(false);
        }
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isDisabled() : bool
    {
        return $this->disabled;
    }
    
    /**
     *
     * @return string
     */
    protected function getTraceFileName(){
        $workbench = $this->getWorkbench();
        $now = \DateTime::createFromFormat('U.u', microtime(true));
        $time = $now->format("Y-m-d H-i-s-u");
        return $workbench->filemanager()->getPathToLogFolder() . DIRECTORY_SEPARATOR . 'traces' . DIRECTORY_SEPARATOR . $time . '.csv';
    }
    
    protected function registerLogHandlers()
    {
        // Log everything
        $workbench = $this->getWorkbench();
        $this->log_handlers = [
            new BufferingHandler(
                new MonologCsvFileHandler(
                    $this->getWorkbench(),
                    "Tracer", 
                    $this->getTraceFileName(), 
                    $workbench->filemanager()->getPathToLogDetailsFolder(),
                    LoggerInterface::DEBUG,
                    LoggerInterface::DEBUG,
                    LoggerInterface::DEBUG
                )
            )
        ];
        foreach ($this->log_handlers as $handler){
            $workbench->getLogger()->appendHandler($handler);
        }
    }
    
    protected function registerEventHandlers()
    {
        $event_manager = $this->getWorkbench()->eventManager();
        
        // Actions
        $event_manager->addListener(OnBeforeActionPerformedEvent::getEventName(), [
            $this,
            'startAction'
        ]);
        $event_manager->addListener(OnActionPerformedEvent::getEventName(), [
            $this,
            'stopAction'
        ]);
        
        // Data Queries
        $event_manager->addListener(OnBeforeConnectEvent::getEventName(), [
            $this,
            'startConnection'
        ]);
        $event_manager->addListener(OnConnectEvent::getEventName(), [
            $this,
            'stopConnection'
        ]);
        $event_manager->addListener(OnBeforeQueryEvent::getEventName(), [
            $this,
            'startDataQuery'
        ]);
        $event_manager->addListener(OnQueryEvent::getEventName(), [
            $this,
            'stopDataQuery'
        ]);
        
        // Performance summary
        $event_manager->addListener(OnBeforeStopEvent::getEventName(), [
            $this,
            'stopWorkbench'
        ]);
        
        // Milestones
        $event_manager->addListener(OnStartEvent::getEventName(), [
            $this,
            'logEvent'
        ], -1000);
        $event_manager->addListener(OnAuthenticatedEvent::getEventName(), [
            $this,
            'logEvent'
        ], -1000);
        $event_manager->addListener(OnBeforeStopEvent::getEventName(), [
            $this,
            'logEvent'
        ], -1000);
        $event_manager->addListener(OnMetaObjectLoadedEvent::getEventName(), [
            $this,
            'logEvent'
        ]);
        $event_manager->addListener(OnContextInitEvent::getEventName(), [
            $this,
            'logEvent'
        ]);
        
        return $this;
    }
    
    public function logEvent(EventInterface $event)
    {
        $this->start($event, $this->getLapName($event), 'event');
    }
    
    public function startAction(ActionEventInterface $event)
    {
        try {
            $msg = $this->getLapName($event);
            $this->getWorkbench()->getLogger()->debug($msg, array());
            $this->start($event->getAction(), $msg, 'action');
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
        }
    }
    
    public function stopAction(ActionEventInterface $event)
    {
        try {
            $ms = $this->stop($event->getAction());
        } catch (\Throwable $e) {
            // FIXME event-not-started exceptions are thrown here when perforimng
            // ContextApi actions. Need to find out why, than reenable the following
            // line. Currently it produces extra trace files with a single error line
            // - this is very confusing!
            // $this->getWorkbench()->getLogger()->logException($e);
        }
        
        try {
            $duration = $ms !== null ? ' in ' . $ms . ' ms' : '';
            $this->getWorkbench()->getLogger()->debug($this->getLapName($event) . ' finished' . $duration . '.', array());
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
        }
    }
    
    /**
     * 
     * @param OnBeforeQueryEvent $event
     */
    public function startDataQuery(OnBeforeQueryEvent $event)
    {
        $this->start($event->getQuery(), $this->getLapName($event), 'query');
    }
    
    /**
     * 
     * @param OnQueryEvent $event
     */
    public function stopDataQuery(OnQueryEvent $event)
    {
        try {
            $query = $event->getQuery();
            
            $ms = $this->stop($query);
            $this->dataQueriesCnt++;
            
            $name = $this->getLapName($event);
            if ($ms !== null) {
                $duration = ' (' . $ms . ' ms)';
                $this->dataQueriesTotalMS += $ms;
            } else {
                $duration = '';
            }
            $this->getWorkbench()->getLogger()->debug($name . $duration, array(), $query);
        } catch (\Throwable $e){
            $this->getWorkbench()->getLogger()->logException($e);
        }
    }
    
    protected function getLapName(EventInterface $event) : string
    {
        switch (true) {
            case $event instanceof DataQueryEventInterface:
                $name = 'Query "' . ($event->getConnection()->hasModel() ? $event->getConnection()->getAlias() : get_class($event->getConnection())) . '"';
                $queryString = str_replace(
                    ["\r", "\n", "\t", "  "],
                    [' ', ' ', '', ''],
                    $event->getQuery()->toString(false)
                    );
                $name .= ': ' . mb_substr($queryString, 0, 50) . (strlen($queryString) > 50 ? '...' : '');
                break;
            case $event instanceof ActionEventInterface:
                $name = 'Action "' . $event->getAction()->getAliasWithNamespace() . '"';
                break;
            default: 
                $name = 'Event ' . StringDataType::substringAfter($event::getEventName(), '.', $event::getEventName(), false, true);
                switch (true) {
                    case $event instanceof OnAuthenticatedEvent:
                        if ($token = $event->getToken()) {
                            $name .= ' (' . $token->getUsername() . ')';
                        }
                        break;
                    case $event instanceof OnMetaObjectLoadedEvent:
                        $name .= ' (' . $event->getObject()->getAliasWithNamespace() . ')';
                        break;
                    case $event instanceof ContextEventInterface:
                        $name .= ' (' . $event->getContext()->getAliasWithNamespace() . ')';
                        break;
                }
                break;
        }
        
        return $name;
    }
    
    public function startConnection(OnBeforeConnectEvent $event)
    {
        $this->start($event->getConnection(), 'Connect ' . $event->getConnection()->getAliasWithNamespace(), 'connection');
        $this->conncetionsCnt++;
    }
    
    public function stopConnection(OnConnectEvent $event)
    {
        try {
            $ms = $this->stop($event->getConnection());
            $this->connectionsTotalMS += $ms;
        } catch (\Throwable $e){
            $this->getWorkbench()->getLogger()->logException($e);
        }
    }
    
    /**
     * 
     * @param OnBeforeStopEvent $event
     */
    public function stopWorkbench(OnBeforeStopEvent $event = null) {
        $this->getWorkbench()->getLogger()->debug('Performance summary: ' . $this->getDurationTotal() . ' ms total, ' . $this->conncetionsCnt . ' connections opened in ' . $this->connectionsTotalMS . ' ms, ' . $this->dataQueriesCnt . ' data queries in ' . $this->dataQueriesTotalMS . ' ms', [], $this);
    }
}