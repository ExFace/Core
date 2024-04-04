<?php
namespace exface\Core\Events\Facades;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\FacadeEventInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Events\CliCommandEventInterface;

/**
 * Event triggered when a command line facade receives a command, but before the command processing was begun
 * 
 * @event exface.Core.Facades.OnFacadeReceivedCliCommand
 * 
 * @author Andrej Kabachnik
 *
 */
class OnCliCommandReceivedEvent extends AbstractEvent implements FacadeEventInterface, CliCommandEventInterface
{
    private $facade = null;
    
    private $command = null;
    
    public function __construct(FacadeInterface $facade, string $command)
    {
        $this->facade = $facade;
        $this->command = $command;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->facade->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\FacadeEventInterface::getFacade()
     */
    public function getFacade() : FacadeInterface
    {
        return $this->facade;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\CliCommandEventInterface::getCliCommand()
     */
    public function getCliCommand() : string
    {
        return $this->command;
    }
}