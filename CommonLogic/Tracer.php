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
                new LogfileHandler("exface", $this->getTraceFileName(), $workbench, LoggerInterface::DEBUG)
                ),
            new BufferingHandler(
                new DebugMessageFileHandler($workbench, $workbench->filemanager()->getPathToLogDetailsFolder(), ".json", LoggerInterface::DEBUG)
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
        $event_manager->addListener(OnBeforeActionPerformedEvent::getEventName(), array(
            $this,
            'startAction'
        ));
        $event_manager->addListener(OnActionPerformedEvent::getEventName(), array(
            $this,
            'stopAction'
        ));
        
        // Data Queries
        $event_manager->addListener(OnBeforeQueryEvent::getEventName(), array(
            $this,
            'startDataQuery'
        ));
        $event_manager->addListener(OnQueryEvent::getEventName(), array(
            $this,
            'stopDataQuery'
        ));
        
        return $this;
    }
    
    public function startAction(ActionEventInterface $event)
    {
        try {
            $this->getWorkbench()->getLogger()->debug('Action "' . $event->getAction()->getAliasWithNamespace() . '" started.', array());
            $this->start($event->getAction());
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
            $this->getWorkbench()->getLogger()->debug('Action "' . $event->getAction()->getAliasWithNamespace() . '" finished' . $duration . '.', array());
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
        }
    }
    
    public function startDataQuery(OnBeforeQueryEvent $event)
    {
        $this->start($event->getQuery());
    }
    
    public function stopDataQuery(OnQueryEvent $event)
    {
        try {
            $query = $event->getQuery();
            
            $ms = $this->stop($query);
            
            $conn = 'Query "' . ($event->getConnection()->hasModel() ? $event->getConnection()->getAlias() : get_class($event->getConnection())) . '"';
            $queryString = str_replace(array("\r", "\n", "\t", "  "), '', $query->toString(false));
            $extract = mb_substr($queryString, 0, 50) . (strlen($queryString) > 50 ? '...' : '');
            $duration = $ms !== null ? ' (' . $ms . ' ms)' : '';
            $this->getWorkbench()->getLogger()->debug($conn . ': ' . $extract . $duration, array(), $query);
        } catch (\Throwable $e){
            $this->getWorkbench()->getLogger()->logException($e);
        }
    }
}
?>