<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\CommonLogic\AbstractActionDeferred;
use exface\Core\CommonLogic\Actions\ServiceParameter;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Workbench\OnCleanUpEvent;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;

/**
 * Triggers various housekeeping procedures: purging older queue messages and monitor data, etc.
 * 
 * What exactly is being done depends on the installed apps. The action merely fires the OnCleanUpEvent,
 * that tells all it's listeners to do their things. 
 * 
 * The action can be performed for specific leaning areas by specifying them directly in the `areas`
 * property. Refer to the app documentation for information about available cleaning areas. For example,
 * the Core includes the areas 
 * 
 * - `monitor` to purge monitor data older than configured in `MONITOR.DAYS_TO_KEEP_ACTIONS` and
 * - `queues` to clean up queue data (each queue prototype "knows" what to do).
 * 
 * If no `areas` are defined, everything will be cleaned up.
 * 
 * @author Andrej Kabachnik
 *
 */
class CleanUp extends AbstractActionDeferred implements iCanBeCalledFromCLI
{
    private $areas = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performImmediately()
     */
    protected function performImmediately(TaskInterface $task, DataTransactionInterface $transaction, ResultMessageStreamInterface $result) : array
    {
        return [];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performDeferred()
     */
    public function performDeferred() : \Generator
    {
        $event = new OnCleanUpEvent($this->getWorkbench(), $this->getAreas());
        $event = $this->getWorkbench()->eventManager()->dispatch($event);
        yield 'Starting cleanup:' . PHP_EOL;
        $cnt = 0;
        foreach ($event->getResultMessages() as $msg) {
            yield '  - ' .  $msg . PHP_EOL;
            $cnt++;
        }
        yield 'Finished cleaning' . ($cnt === 0 ? ' (nothing to do)' : '') . '.' . PHP_EOL;
        return;
    } 
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliArguments()
     */
    public function getCliArguments(): array
    {
        return [
            (new ServiceParameter($this))->setName('areas')->setDescription('Comma-separated list of things to clean: "monitor", "queues" or "logs"')
        ];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliOptions()
     */
    public function getCliOptions(): array
    {
        return [];
    }
    
    protected function getAreas() : ?array
    {
        return $this->areas;
    }
    
    /**
     * Areas to clean: queues, monitor or app-specific areas
     * 
     * @uxon-property areas
     * @uxon-type array
     * @uxon-template ["queues", "monitor]
     * 
     * @param UxonObject $value
     * @return CleanUp
     */
    public function setAreas(UxonObject $value) : CleanUp
    {
        $this->areas = $value->toArray();
        return $this;
    }
}