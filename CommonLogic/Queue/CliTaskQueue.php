<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\CommonLogic\Tasks\ResultMessageStream;
use exface\Core\CommonLogic\Traits\TranslatablePropertyTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Queues\QueueRuntimeError;
use exface\Core\Facades\ConsoleFacade\CliCommandRunner;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

/**
 * Performs CLI command(s) from the task parameter `cmd` - similar to the WebConsoleFacade.
 * 
 * The `command_timeout` property sets the maximum number of seconds a CLI command is allowed to 
 * run before it is forcefully terminated. Without this limit, a command that hangs indefinitely 
 * — due to a deadlock, waiting for user input, or an unresponsive external service — would keep 
 * the PHP process alive forever and leave the queue item stuck in IN_PROGRESS state with no way 
 * to recover automatically. By enforcing a timeout, the process is killed cleanly.
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
    private ?string $outputFolder = null;

    /**
     * Runs the queue's CLI command(s) from the task's `cmd` parameter and streams
     * the collected output into a ResultMessageStream.
     *
     * The `cmd` task parameter may hold a single command (string) or a list of
     * commands (array / UxonObject). This method normalizes it to an ordered list and
     * executes the commands one after another inside one and the same queue run - the
     * command list is iterated exactly once, so N commands do NOT cause N queue runs.
     *
     * The combined output is computed eagerly here and stored as a plain string on the
     * result. This is deliberate: the result message is read more than once downstream
     * (once in `onRunPerformTask()` to drain the stream, again in `saveResult()` when
     * the queue item is persisted). Storing a finished string instead of a lazy
     * generator guarantees the commands can never be re-executed by a second
     * `getMessage()` call.
     *
     * In addition to the in-memory result message, every chunk of output is written to
     * a live log file (when `output_folder` is configured) and flushed to the OS
     * immediately via fflush(). This is the key reliability feature: if the PHP process
     * is killed externally (php-fpm/web server timeout) before this method returns, the
     * partial Behat/CLI output up to that point is already persisted on disk, so the
     * reason a test got stuck can still be diagnosed even when the queue item remains
     * in IN_PROGRESS. The file handle is closed in a finally block, so it is released
     * even if an exception propagates out of the command loop.
     *
     * @param TaskInterface $task
     *
     * @throws \exface\Core\Exceptions\Queues\QueueRuntimeError
     *      if the `cmd` parameter is neither a string, array nor UxonObject, or if a
     *      command is not matched by any of the configured `allowed_commands`.
     *
     * @return ResultInterface
     *
     * @see AbstractInternalTaskQueue::performTask()
     */
    protected function performTask(TaskInterface $task) : ResultInterface
    {
        $commands = $this->normalizeCommands($task->getParameter('cmd'));

        $projectRoot = $this->getWorkbench()->getInstallationPath();
        $envVars = $this->buildEnvironmentVars();
        $timeout = $task->hasParameter('timeout')
            ? (float) $task->getParameter('timeout')
            : $this->getCommandTimeout();
        // Normalize ignored_exit_codes — UXON delivers arrays as UxonObject, values as strings.
        // runCliCommand() uses strict in_array(), so values must be integers.
        $rawExitCodes = $task->hasParameter('ignored_exit_codes') ? $task->getParameter('ignored_exit_codes') : [];
        $ignoredExitCodes = array_map('intval', $rawExitCodes instanceof UxonObject ? $rawExitCodes->toArray() : (array) $rawExitCodes);

        // Open a live output file so partial output survives even if the PHP process is
        // killed before performTask() returns (php-fpm/web server timeout). Returns null
        // when `output_folder` is not configured, in which case file logging is skipped.
        $logFile = $this->getOutputFilePath($task);
        $fh = null;
        if ($logFile !== null) {
            @mkdir(dirname($logFile), 0775, true);
            $fh = @fopen($logFile, 'a');
        }

        // Collected output of every command in execution order. Declared outside the
        // loop so output produced before a failing command is preserved instead of
        // being discarded when an exception propagates out of the loop.
        $allOutputs = [];

        try {
            // Run every command one after another within this single queue run.
            foreach ($commands as $command) {
                $this->assertCommandAllowed($command);

                if ($fh !== null) {
                    fwrite($fh, PHP_EOL . '=== ' . date('Y-m-d H:i:s') . ' START: ' . $command . ' ===' . PHP_EOL);
                    fflush($fh);
                }

                foreach (CliCommandRunner::runCliCommand($command, $envVars, $timeout, $projectRoot, false, $ignoredExitCodes) as $output) {
                    $allOutputs[] = $output;
                    if ($fh !== null) {
                        // Write each chunk and push it to the OS right away, so a process
                        // kill immediately afterwards cannot lose buffered output.
                        fwrite($fh, $output);
                        fflush($fh);
                    }
                }

                if ($fh !== null) {
                    fwrite($fh, PHP_EOL . '=== ' . date('Y-m-d H:i:s') . ' END: ' . $command . ' ===' . PHP_EOL);
                    fflush($fh);
                }
            }
        } catch (\Throwable $e) {
            // Preserve the output gathered so far by attaching it to the error,
            // otherwise everything collected in $allOutputs is lost on propagation.
            $collected = implode(PHP_EOL, $allOutputs);
            if ($collected !== '') {
                throw new QueueRuntimeError($this, $e->getMessage() . PHP_EOL . PHP_EOL . 'Output before failure:' . PHP_EOL . $collected, null, $e);
            }
            throw $e;
        } finally {
            // Always release the file handle, even if an exception propagates out of
            // the command loop above.
            if ($fh !== null) {
                fclose($fh);
            }
        }

        // Compute the message once and set it as a plain string so reading it again
        // downstream never triggers another execution of the commands.
        $result = new ResultMessageStream($task);
        $result->setMessage(implode(PHP_EOL, $allOutputs));

        return $result;
    }

    /**
     * Normalizes the `cmd` task parameter into a flat, ordered list of command strings.
     *
     * Extracted from performTask() because `cmd` may legitimately arrive as a string,
     * an array or a UxonObject, and the queue must treat all of these as a single
     * ordered list of commands to run sequentially in one run. Keeping the conversion
     * here makes performTask() read as "run this list" with no branching noise.
     *
     * @param mixed $commands
     * @throws QueueRuntimeError
     * @return string[]
     */
    protected function normalizeCommands($commands) : array
    {
        switch (true) {
            case is_string($commands):
                return [$commands];
            case $commands instanceof UxonObject:
                return $commands->toArray();
            case is_array($commands):
                return $commands;
            default:
                throw new QueueRuntimeError($this, 'Cannot get command from `cmd` parameter of queued task: expecting array or string, got ' . gettype($commands));
        }
    }

    /**
     * Ensures a command matches at least one configured allowed-command pattern.
     *
     * Extracted from performTask() so the security check is separated from the
     * execution loop, can be reused and tested on its own, and so the loop body stays
     * focused on running commands. Throws if the command is not whitelisted.
     *
     * @param string $command
     * @throws QueueRuntimeError
     * @return void
     */
    protected function assertCommandAllowed(string $command) : void
    {
        $normalized = str_replace('\\', '/', $command);
        foreach ($this->getAllowedCommands() as $allowedCommand) {
            if (preg_match($allowedCommand, $normalized) === 1) {
                return;
            }
        }
        throw new QueueRuntimeError($this, 'Command "' . $command . '" not allowed in CLI queue "' . $this->getAliasWithNamespace() . '"!');
    }

    /**
     * Builds the absolute path of the live output file for the given task.
     *
     * The configured `output_folder` is ALWAYS treated as relative to the PHP current
     * working directory (getcwd()). This is intentional: the absolute installation path
     * may contain a deployment/release-specific folder name that cannot be known when
     * configuring the queue, so the user only provides the relative subfolder and cwd
     * is prepended at runtime.
     *
     * A unique, timestamped filename is generated per call so stuck items can be matched
     * against the queue item's ENQUEUED_ON time. Returns null when no output folder is
     * configured (file logging disabled).
     *
     * @param TaskInterface $task
     * @return string|null
     */
    protected function getOutputFilePath(TaskInterface $task) : ?string
    {
        $folder = $this->getOutputFolder();
        if ($folder === null || $folder === '') {
            return null; // file logging disabled
        }

        // Always resolve relative to the current working directory.
        $base = rtrim(getcwd(), '/\\');
        $folder = $base . DIRECTORY_SEPARATOR . trim($folder, '/\\');

        $filename = 'cli_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.log';
        return $folder . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Returns the configured output folder (relative to cwd) or null if disabled.
     *
     * @return string|null
     */
    public function getOutputFolder() : ?string
    {
        return $this->outputFolder;
    }

    /**
     * Folder to write live CLI/Behat output to, so it survives an external process kill.
     *
     * The path is always relative to the current working directory (cwd) at runtime.
     * Do NOT use an absolute path here, as the deployment/release directory is not known
     * in advance. Leave empty to disable file logging.
     *
     * @uxon-property output_folder
     * @uxon-type string
     *
     * @param string $path
     * @return CliTaskQueue
     */
    public function setOutputFolder(string $path) : CliTaskQueue
    {
        $this->outputFolder = $path;
        return $this;
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
        return $this->commandTimeout ?? 600.0;
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