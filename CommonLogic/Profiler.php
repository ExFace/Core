<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\Stopwatch\Stopwatch;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\ActionEvent;
use exface\Core\Events\DataConnectionEvent;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

class Profiler implements ExfaceClassInterface
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

    public function startAction(ActionEvent $event)
    {
        $this->stopwatch->start($event->getAction()->getId());
    }

    public function stopAction(ActionEvent $event)
    {
        try {
            //$this->getWorkbench()->getLogger()->debug('Action ' . $event->getAction()->getAliasWithNamespace() . ' performed.', array());
            $this->stopwatch->stop($event->getAction()->getId());
        } catch (\Exception $e) {
            $this->getWorkbench()->getLogger()->logException($e);
        }
    }

    public function startDataQuery(DataConnectionEvent $event)
    {
        $category = new UxonObject();
        $query_string = $event->getCurrentQuery()->exportString();
        $category->setProperty('query', $query_string);
        $this->stopwatch->start($query_string, $category->toJson());
    }

    public function stopDataQuery(DataConnectionEvent $event)
    {
        try {
            $query = $event->getCurrentQuery();
            $this->getWorkbench()->getLogger()->debug($event->getDataConnection()->getAlias() . ': ' . substr($query->toString(), 0, 50), array(), $query);
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
        $event_manager->addListener('#.Action.Perform.Before', array(
            $this,
            'startAction'
        ));
        $event_manager->addListener('#.Action.Perform.After', array(
            $this,
            'stopAction'
        ));
        
        // Data Queries
        $event_manager->addListener('#.DataConnection.Query.Before', array(
            $this,
            'startDataQuery'
        ));
        $event_manager->addListener('#.DataConnection.Query.After', array(
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
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
}
?>