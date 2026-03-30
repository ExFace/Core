<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Facades\ConsoleFacade\SymfonyCommandAdapter;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Tasks\CliTaskInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class CliTask extends GenericTask implements CliTaskInterface
{    
    private string $cliCommandName;
    private array $cliArguments;
    private array $cliOptions;

    public function __construct(WorkbenchInterface $workbench, string $commandName, array $arguments = [], array $options = [], FacadeInterface $facade = null)
    {
        parent::__construct($workbench, $facade);
        $this->cliCommandName = $commandName;
        $this->cliArguments = $arguments;
        $this->cliOptions = $options;
    }
    
    public function getCliCommandName() : string
    {
        return $this->cliCommandName;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::getCliArguments()
     */
    public function getCliArguments() : array
    {
        return $this->cliArguments;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::getCliArgument()
     */
    public function getCliArgument(string $name)
    {
        return $this->getCliArguments()[$name];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::hasCliArgument()
     */
    public function hasCliArgument(string $name) : bool
    {
        return $this->getCliArguments()[$name] !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::getCliOptions()
     */
    public function getCliOptions() : array
    {
        return $this->cliOptions;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::getCliOption()
     */
    public function getCliOption(string $name)
    {
        return $this->getCliOptions()[$name];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::hasCliOption()
     */
    public function hasCliOption(string $name) : bool
    {
        return $this->getCliOptions()[$name] !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\GenericTask::getParameters()
     */
    public function getParameters() : array
    {
        // Overwrite CLI options with arguments on conflict. Conflicts happened on
        // Windows when calling the action directly from CLI - arguments had 
        // included the options too, but with `false` as value.
        return array_merge($this->getCliOptions(), $this->getCliArguments());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\GenericTask::getParameter()
     */
    public function getParameter($name)
    {
        return $this->getParameters()[$name];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\GenericTask::hasParameter()
     */
    public function hasParameter($name) : bool
    {
        return $this->hasCliArgument($name) || $this->hasCliOption($name);
    }
}