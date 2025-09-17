<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\CommonLogic\Tasks\ResultMessageStream;
use exface\Core\CommonLogic\Traits\TranslatablePropertyTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Queues\QueueRuntimeError;
use exface\Core\Facades\ConsoleFacade\CommandRunner;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

/**
 * Performs CLI command(s) from the task parameter `cmd` - similar to the WebConsoleFacade.
 * 
 * @author Andrej Kabachnik
 *
 */
class CliTaskQueue extends SyncTaskQueue
{
    use TranslatablePropertyTrait;
    
    private ?float $commandTimeout = null;
    private array $environmentVars = [];
    private array $environmentVarsInherit = [];
    private array $allowedCommands = [];

    /**
     * {@inheritDoc}
     * @see AbstractInternalTaskQueue::performTask()
     */
    protected function performTask(TaskInterface $task) : ResultInterface
    {
        $commands = $task->getParameter('cmd');

        // Normalize to array
        if (!is_array($commands)) {
            $commands = $commands->toArray();
        }

        $projectRoot = $this->getWorkbench()->getInstallationPath();
        $envVars = $this->buildEnvironmentVars();
        $timeout = $task->getParameter('timeout');
        $result = new ResultMessageStream($task);

        // Store each command's outputs
        $allOutputs = [];

        foreach ($commands as $command) {
            // Check if command allowed
            $allowed = FALSE;
            $normalized = str_replace('\\', '/', $command);
            foreach ($this->getAllowedCommands() as $allowedCommand){
                $match = preg_match($allowedCommand, $normalized);
                if($match === 1){
                    $allowed = TRUE;
                    break;
                }
            }
            if (! $allowed){
                throw new QueueRuntimeError($this, 'Command "' . $command . '" not allowed in CLI queue "' . $this->getAliasWithNamespace() . '"!');
            }

            // Run each command and collect the output
            foreach (CommandRunner::runCliCommand($command, $envVars, $timeout, $projectRoot, false) as $output) {
                $allOutputs[] = $output;
            }
        }

        // Set all outputs as message in result
        $result->setMessage(implode(PHP_EOL, $allOutputs));

        return $result;
    }

    /**
     * Returns Array of regular expressions with allowed commands
     *
     * @return array
     */
    public function getAllowedCommands() :array
    {
        return $this->allowedCommands;
    }

    /**
     * Set array of regular expressions to check if a command is allowed.
     *
     * Each command is treated as aregular expression, so take care of placing it
     * between valid regex delimiters: e.g. type `/whoami/i` to allow the `whoami`
     * command (the `/` would be the regex delimiter and `i` - the regex flag for
     * case-insensitive matching).
     *
     * @uxon-property allowed_commands
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param UxonObject $uxon
     * @return CliTaskQueue
     */
    public function setAllowedCommands(UxonObject $uxon) : CliTaskQueue
    {
        $this->allowedCommands = $uxon->toArray();
        return $this;
    }

    /**
     * Set timeout for the commands in seconds.
     * Default is 600.
     *
     * @uxon-property command_timeout
     * @uxon-type integer
     * @uxon-default 600
     *
     * @param string $timeout
     * @return CliTaskQueue
     */
    public function setCommandTimeout(string $timeout) : CliTaskQueue
    {
        $this->commandTimeout = floatval($timeout);
        return $this;
    }

    /**
     * Return the Timeout for the commands in seconds.
     * To deactivate timeout set it to '0.0'.
     *
     * @return float
     */
    public function getCommandTimeout() : float
    {
        return $this->commandTimeout;
    }


    /**
     * Environment variables to be used when executing commands as key-value-pairs.
     *
     * Static formulas like `=User('username')` are supported in values.
     *
     * @uxon-property environment_vars
     * @uxon-type object
     * @uxon-template {"VAR_NAME": "value"}
     * @uxon-required true
     *
     * @param UxonObject $uxon
     * @return CliTaskQueue
     */
    public function setEnvironmentVars(UxonObject $uxon) : CliTaskQueue
    {
        foreach ($uxon->toArray() as $var => $val) {
            $this->environmentVars[$var] = $this->evaluatePropertyExpression($val);
        }
        return $this;
    }

    /**
     * Returns array of environment variables to be used executing the commands
     *
     * @return array
     */
    public function getEnvironmentVars() : array
    {
        return $this->environmentVars;
    }

    /**
     * Names of environment variables to inherit along with `environment_vars` defined manually.
     *
     * In addition to manually defined `environment_vars` your can use `environment_vars_inherit`
     * to list variables to fetch via `getenv()` and merge with `environment_vars` defined for
     * this widget. This is usefull to get user-specific variables like `APPDATA`, etc.
     *
     * @uxon-property environment_vars_inherit
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param UxonObject $uxon
     * @return CliTaskQueue
     */
    public function setEnvironmentVarsInherit(UxonObject $uxon) :CliTaskQueue
    {
        $this->environmentVarsInherit = $uxon->toArray();
        return $this;
    }

    /**
     *
     * @return string[]
     */
    public function getEnvironmentVarsInherit() : array
    {
        return $this->environmentVarsInherit;
    }

    /**
     * @return array
     */
    protected function buildEnvironmentVars() : array
    {
        $envVars = [];
        if (! empty($inheritVars = $this->getEnvironmentVarsInherit())) {
            foreach (getenv() as $var => $val) {
                if (in_array($var, $inheritVars)) {
                    $envVars[$var] = $val;
                }
            }
        }
        $envVars = array_merge($envVars, $this->getEnvironmentVars());
        return $envVars;
    }
}