<?php
namespace exface\Core\Facades\ConsoleFacade;

use exface\Core\Exceptions\Facades\FacadeRoutingError;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Facades\ConsoleFacade\Interfaces\FacadeCommandLoaderInterface;

/**
 * A command loader for Symfony Console, that creates commands from actions listed
 * under FACADES.CONSOLE.ACTION_COMMANDS_ALLOWED in the core config.
 * 
 * The command loader wraps actions as native Symfony commands using the
 * SymfonyCommandAdapter.
 * 
 * @author Andrej Kabachnik
 *
 */
class CommandLoader implements FacadeCommandLoaderInterface
{
    private $facade = null;
    
    private $cliActions = null;
    
    public function __construct(FacadeInterface $facade)
    {
        $this->facade = $facade;    
    }
    
    public function get($name)
    {
        $name = mb_strtolower(trim($name));
        $commands = $this->getCommandActionMap();
        if ($commands[$name] === null) {
            throw new FacadeRoutingError('Command "' . $name . '" not found!');
        }
        try {
            $action = ActionFactory::createFromString($this->getWorkbench(), $commands[$name]);
            $command = new SymfonyCommandAdapter($this, $action);
        } catch (\Throwable $e) {
            $command = new ErrorPlaceholderCommand($e, '** Could not load', $name);
        }
        return $command;
    }

    public function has($name)
    {
        $name = mb_strtolower(trim($name));
        return $this->getCommandActionMap()[$name] !== null;
    }

    public function getNames()
    {
        return array_keys($this->getCommandActionMap());
    }
    
    public function getFacade() : FacadeInterface
    {
        return $this->facade;
    }
    
    protected function getCommandActionMap() : array
    {
        if ($this->cliActions === null) {
            $this->cliActions = [];
            foreach ($this->getWorkbench()->getConfig()->getOption('FACADES.CONSOLE.ACTION_COMMANDS_ALLOWED') as $alias => $enabled) {
                if ($enabled === false) {
                    continue;
                }
                $this->cliActions[$this->getCommandNameFromAlias($alias)] = $alias;
            }
        }
        return $this->cliActions;
    }
    
    /**
     * 
     * @return WorkbenchInterface
     */
    protected function getWorkbench() : WorkbenchInterface
    {
        return $this->facade->getWorkbench();
    }
    
    public function getCommandNameFromAlias(string $alias) : string
    {
        $pos = strrpos($alias, '.');
        
        if($pos !== false)
        {
            $alias = substr_replace($alias, ':', $pos, 1);
        }
        
        return mb_strtolower($alias);
    }
}