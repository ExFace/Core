<?php
namespace exface\Core\Facades\ConsoleFacade;

use exface\Core\Exceptions\Facades\FacadeRoutingError;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Facades\ConsoleFacade\Interfaces\FacadeCommandLoaderInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Factories\SelectorFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use Symfony\Component\Console\Command\Command;

/**
 * A command loader for Symfony Console, that creates commands from actions implementing
 * the iCanBeCalledFromCLI interface.
 * 
 * Command names are derived from action aliases: e.g. the action `exface.Core.ClearCache` 
 * is made available via the command `exface.Core:ClearCache`. Command names are case
 * insensitive as long as they remain unambiguous.
 * 
 * To make the actions work with Symfony Console, they are wrapped as native Symfony commands 
 * using the SymfonyCommandAdapter.
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
    
    public function get(string $name): Command
    {        
        try {
            $action = ActionFactory::createFromString($this->getWorkbench(), $this->getAliasFromCommandName($name));
            $command = new SymfonyCommandAdapter($this, $action);
        } catch (\Throwable $e) {
            $command = new ErrorPlaceholderCommand($e, '** Could not load', $name);
        }
        return $command;
    }
    
    protected function getAliasFromCommandName(string $command) : string
    {
        $commands = $this->getCommandActionMap();
        $found = null;
        
        list ($cspace, $cname) = explode(':', $command);
        foreach ($commands as $name => $alias) {
            list($namespace, $name) = explode(':', $name);
            if (stripos($namespace, $cspace) !== false && stripos($name, $cname) === 0) {
                if ($found === null) {
                    $found = $alias;
                } else {
                    var_dump('non-unique', $command);
                    throw new FacadeRoutingError('Ambiguous command "' . $command . "!'");
                }
            }
        }
        
        if ($found === null) {
            throw new FacadeRoutingError('Command "' . $command . '" not found!');
        }
        return $found;
    }

    public function has($name): bool
    {
        return true;
        try {
            $this->getAliasFromCommandName($name);
            return true;
        } catch (FacadeRoutingError $e) {
            return false;
        }
    }

    public function getNames(): array
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
            
            // Load Prototypes
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.ACTION');
            $ds->getColumns()->addMultiple(['NAME', 'PATH_RELATIVE', 'PATHNAME_RELATIVE']);
            $ds->dataRead();
            if ($ds->isEmpty() === true) {
                throw new FacadeRuntimeError('This installation seems corrupt: not a single action was found! Please reinstall the workbench!');
            }
            foreach ($ds->getRows() as $row) {
                try {
                    $selector = SelectorFactory::createActionSelector($this->getWorkbench(), $row['PATHNAME_RELATIVE']);
                    $class = $this->getWorkbench()->getCoreApp()->getPrototypeClass($selector);
                    if (is_a($class, '\\' . iCanBeCalledFromCLI::class, true) === false) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    $err = new FacadeLogicError('Cannot check action "' . $row['PATHNAME_RELATIVE'] . "' for compatibility with ConsoleFacade: " . $e->getMessage(), null, $e);
                    $this->getWorkbench()->getLogger()->logException($err);
                    continue;
                }
                
                $dot = AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER;
                $namespace = str_replace(['\\Actions', '\\'], ['', $dot], StringDataType::substringBefore($class, '\\', '', false, true));
                $alias = $namespace . $dot . $row['NAME'];
                $this->cliActions[$this->getCommandNameFromAlias($alias)] = $alias;
            }
            
            // TODO load model action for command-prototypes
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
        
        return $alias;
    }
}