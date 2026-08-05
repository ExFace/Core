<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\DataTypes\DateDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Facades\ConsoleFacade\CliCommandRunner;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Tasks\CliTaskInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * This task represents a single CLI command
 * 
 * @author Andrej Kabachnik
 *
 */
class CliTask extends GenericTask implements CliTaskInterface
{    
    private ?string $cliCommandName;
    private array $cliArguments;
    private array $cliOptions;
    private ?int $timeoutPerCommand = null;

    public function __construct(WorkbenchInterface $workbench, ?string $commandName = null, array $arguments = [], array $options = [], FacadeInterface $facade = null)
    {
        parent::__construct($workbench, $facade);
        $this->cliCommandName = $commandName;
        $this->cliArguments = $arguments;
        $this->cliOptions = $options;
    }
    
    public function getCliCommandName() : string
    {
        if ($this->cliCommandName === null) {
            throw new RuntimeException('No command found in CLI task. It must either be passed through the constructor or via UXON');
        }
        return $this->cliCommandName;
    }
    
    public function getCliCommand() : string
    {
        return $this->getCliCommandName() 
            . ' ' . implode(' ', $this->getCliArguments()) 
            . ' ' . (! empty($this->getCliOptions()) ? '--' : '') . implode(' --', $this->getCliOptions());
    }

    /**
     * The CLI command to be executed
     * 
     * @uxon-property command
     * @uxon-type string
     * @uxon-required true
     * 
     * @param string $command
     * @return CliTask
     */
    protected function setCommand(string $command) : CliTask
    {
        list($commandName, $args, $opts) = CliCommandRunner::parseCommand($command);
        
        $this->cliCommandName = $commandName;
        $this->cliArguments = $args;
        $this->cliOptions = $opts;
        return $this;
    }

    /**
     * Alias for the setCommand() method for backwards compatibility with older UXONs
     * 
     * @param string $command
     * @return CliTask
     */
    protected function setCmd(string $command) : CliTask
    {
        return $this->setCommand($command);
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

    /**
     * Maximum time for each command in this task to run.
     * 
     * Can be set either as a plain number of seconds (e.g. `600`) or as a
     * human-readable interval:
     * 
     * - Supports `year(s)`, `month(s)`, `week(s)`, `day(s)`, `hour(s)`, `minute(s)`, `second(s)`.
     * - Concatenate with `+`.
     * - For example: `10 minutes`, `1 hour + 30 minutes`, but also `600` for 10 minutes.
     * 
     * @uxon-property timeout_per_command
     * @uxon-type string
     * 
     * @param string|int $timeout
     * @return $this
     * @throws \exface\Core\Exceptions\InvalidArgumentException
     */
    protected function setCommandTimeout(string|int $timeout) : CliTask
    {
        if (is_int($timeout) || is_numeric($timeout)) {
            $this->timeoutPerCommand = (int) $timeout;
            return $this;
        }
        try {
            $interval = DateDataType::castInterval($timeout);
            $reference = new \DateTimeImmutable('@0');
            $this->timeoutPerCommand = $reference->add($interval)->getTimestamp() - $reference->getTimestamp();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid value "' . $timeout . '" for `timeout_per_command` configuration', null, $e);
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getCommandTimeout() : int
    {
        return $this->timeoutPerCommand ?? $this->getParameter('timeout');
    }
}