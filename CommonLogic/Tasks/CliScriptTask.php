<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\CommonLogic\UxonObject;
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
    protected function setCommand(UxonObject|string|array $commands) : CliScriptTask
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
     * @param string $command
     * @return CliTask
     */
    protected function setCmd(UxonObject|string|array $commands) : CliScriptTask
    {
        return $this->setCommand($commands);
    }

    /**
     * Maximum number of seconds for each command in this task to run
     * 
     * @uxon-property timeout_per_command
     * @uxon-type integer
     * 
     * @param int $timeout
     * @return $this
     */
    protected function setCommandTimeout(int $timeout) : CliScriptTask
    {
        $this->commandTimeout = $timeout;
        return $this;
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
}