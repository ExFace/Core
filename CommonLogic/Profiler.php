<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\Stopwatch\Stopwatch;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Events\DataConnection\OnBeforeQueryEvent;
use exface\Core\Events\DataConnection\OnQueryEvent;
use exface\Core\Interfaces\Events\ActionEventInterface;

class Profiler implements WorkbenchDependantInterface
{

    private $stopwatch = null;

    private $offset = 0;
    
    private $workbench = null;

    public function __construct(Workbench $workbench)
    {
        $this->workbench = $workbench;
        $this->stopwatch = new Stopwatch();
        $this->start();
        $this->registerListeners();
    }

    public function startAction(ActionEventInterface $event)
    {
        $this->stopwatch->start($event->getAction()->getId());
    }

    public function stopAction(ActionEventInterface $event)
    {
        try {
            //$this->getWorkbench()->getLogger()->debug('Action ' . $event->getAction()->getAliasWithNamespace() . ' performed.', array());
            $this->stopwatch->stop($event->getAction()->getId());
        } catch (\Exception $e) {
            $this->getWorkbench()->getLogger()->logException($e);
        }
    }

    public function startDataQuery(OnBeforeQueryEvent $event)
    {
        $category = new UxonObject();
        $query_string = $event->getQuery()->exportString();
        $category->setProperty('query', $query_string);
        $this->stopwatch->start($query_string, $category->toJson());
    }

    public function stopDataQuery(OnQueryEvent $event)
    {
        try {
            $query = $event->getQuery();
            $message = $event->getConnection()->getAlias() . ': ' . substr(str_replace(array("\r", "\n", "\t", "  "), '', $query->toString(false)), 0, 50);
            $this->getWorkbench()->getLogger()->debug($message, array(), $query);
            $this->stopwatch->stop($query->exportString());
        } catch (\Throwable $e){
            $this->getWorkbench()->getLogger()->logException($e);
        }
    }

    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->setProperty('TOTAL', $this->stopwatch->getEvent('TOTAL')->getDuration());
        return $uxon;
    }

    public function start()
    {
        $this->stopwatch->start('TOTAL');
    }
    
    public function stop()
    {
        $this->stopwatch->stop('TOTAL');
    }
    
    protected function registerListeners()
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

    

    public function getOffset()
    {
        return $this->offset;
    }

    /**
     *
     * @param integer $milliseconds            
     */
    public function setOffset($milliseconds)
    {
        $this->offset = $milliseconds;
        return $this;
    }

    public function getActionDuration(ActionInterface $action)
    {
        try {
            $result = $this->stopwatch->getEvent($action->getId())->getDuration();
        } catch (\Exception $e) {
            $result = NULL;
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
}
?>