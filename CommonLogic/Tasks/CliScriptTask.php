<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Task containing multiple CLI commands - e.g. a bash or cmd script
 * 
 * @author Andrej Kabachnik
 *
 */
class CliScriptTask extends GenericTask
{    
    private array $commands;
    private ?int $commandTimeout = null;
    private array $ignoredExitCodes = [];

    public function __construct(WorkbenchInterface $workbench, array $commands = [], FacadeInterface $facade = null)
    {
        parent::__construct($workbench, $facade);
        $this->commands = $commands;
    }
    
    public function getCommands() : array
    {
        return $this->commands;
    }

    /**
     * Array of commands to be executed
     * 
     * @uxon-property commands
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param string $commands
     * @return CliTask
     */
    protected function setCommands(UxonObject|string|array $commands) : CliScriptTask
    {
        switch (true) {
            case $commands instanceof UxonObject:
                $this->commands = $commands->toArray();
                break;
            case is_string($commands):
                $this->commands = [$commands];
                break;
            default:
                $this->commands = $commands;
        }
        return $this;
    }

    /** 
     * @deprecated use setCommands() instead
     * 
     * @param string $command
     * @return CliTask
     */
    protected function setCmd(UxonObject|string|array $commands) : CliScriptTask
    {
        return $this->setCommands($commands);
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
    protected function setCommandTimeout(string|int $timeout) : CliScriptTask
    {
        $this->commandTimeout = $this->parseTimeoutToSeconds($timeout);
        return $this;
    }

    /**
     * Converts a timeout value into a number of seconds.
     * 
     * Plain integers (or numeric strings) are treated as seconds directly. Any
     * other string is parsed as a human-readable interval (e.g. `10 minutes`)
     * via DateDataType::castInterval() and converted into seconds.
     * 
     * @param string|int $timeout
     * @return int
     * @throws InvalidArgumentException
     */
    protected function parseTimeoutToSeconds(string|int $timeout) : int
    {
        if (is_int($timeout) || is_numeric($timeout)) {
            return (int) $timeout;
        }
        try {
            $interval = DateDataType::castInterval($timeout);
            $reference = new \DateTimeImmutable('@0');
            return $reference->add($interval)->getTimestamp() - $reference->getTimestamp();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid value "' . $timeout . '" for `timeout_per_command` configuration', null, $e);
        }
    }

    /**
     * @return int|null
     */
    public function getCommandTimeout() : ?int
    {
        return $this->commandTimeout ?? $this->getParameter('timeout');
    }

    /**
     * @return int[]
     */
    public function getIgnoredExitCodes() : array
    {
        return $this->ignoredExitCodes;
    }

    /**
     * Array of CLI exit codes NOT to treat as errors
     * 
     * @uxon-property ignored_exit_codes
     * @uxon-type array
     * @uxon-template [2]
     * 
     * @param UxonObject|array $ignoredExitCodes
     * @return $this
     */
    protected function setIgnoredExitCodes(UxonObject|array $ignoredExitCodes) : CliScriptTask
    {
        $array = $ignoredExitCodes instanceof UxonObject ? $ignoredExitCodes->toArray() : $ignoredExitCodes;
        $this->ignoredExitCodes = array_map('intval', $array);
        return $this;
    }

    /**
     * Export this CLI task to UXON.
     *
     * GenericTask::exportUxonObject() only serialises the generic task properties
     * and has no knowledge of the CLI-specific ones, so without this override
     * commands, command_timeout and ignored_exit_codes are silently dropped. The
     * concrete class name is written too, so the task can be re-instantiated
     * correctly when read back from the queue.
     *
     * The array-valued properties are wrapped in a UxonObject - a plain PHP array
     * is not a valid node in the UXON tree and would not serialise cleanly.
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject() : UxonObject
    {
        $uxon = parent::exportUxonObject();
        if (! empty($this->getCommands())) {
            $uxon->setProperty('commands', new UxonObject($this->getCommands()));
        }
        if ($this->getCommandTimeout() !== null) {
            $uxon->setProperty('command_timeout', $this->getCommandTimeout());
        }
        if (! empty($this->getIgnoredExitCodes())) {
            $uxon->setProperty('ignored_exit_codes', new UxonObject($this->getIgnoredExitCodes()));
        }
        return $uxon;
    }
}