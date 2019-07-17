<?php
namespace exface\Core\Facades\ConsoleFacade;

use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use exface\Core\Exceptions\Facades\FacadeRoutingError;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\ActionFactory;

class CommandLoader implements CommandLoaderInterface
{
    private $workbench = null;
    
    private $cliActions = null;
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;    
    }
    
    public function get($name)
    {
        $name = mb_strtolower(trim($name));
        $commands = $this->getCommandActionMap();
        if ($commands[$name] === null) {
            throw new FacadeRoutingError('Command "' . $name . '" not found!');
        }
        try {
            $action = ActionFactory::createFromString($this->workbench, $commands[$name]);
            $command = new SymfonyCommandAdapter($action);
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
    
    protected function getCommandActionMap() : array
    {
        if ($this->cliActions === null) {
            $this->cliActions = [];
            foreach ($this->workbench->getConfig()->getOption('FACADES.CONSOLE.ACTION_COMMANDS_ALLOWED') as $alias => $enabled) {
                if ($enabled === false) {
                    continue;
                }
                $this->cliActions[ConsoleFacade::convertAliasToCommandName($alias)] = $alias;
            }
        }
        return $this->cliActions;
    }
}