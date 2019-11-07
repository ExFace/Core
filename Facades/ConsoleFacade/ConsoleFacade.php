<?php
namespace exface\Core\Facades\ConsoleFacade;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\CommonLogic\Traits\AliasTrait;
use Symfony\Component\Console\Application;
use exface\Core\Interfaces\Tasks\CliTaskInterface;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;
use Symfony\Component\Console\Input\StringInput;
use exface\Core\DataTypes\StringDataType;
use Symfony\Component\Console\Input\InputArgument;
use exface\Core\Factories\TaskFactory;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Command line interface facade based on Symfony Console.
 * 
 * To see all commands available in your installation, type the
 * following in a terminal (Windows CMD, PowerShell, Linux Bash, etc.):
 * 
 * ```
 * vendor/bin/action
 * 
 * ```
 * 
 * For detailed information about the use of a command, type 
 * 
 * ```
 * vendor/bin/action <command-name> -h
 * 
 * ``` 
 * 
 * Command names are derived from action aliases: e.g. the action 
 * `exface.Core.ClearCache` corresponds to the command 
 * `exface.Core:ClearCache`. Command names are case insensitive as long 
 * as they remain unambiguous.
 * 
 * You can use a short syntax for command names by typing only the beginning
 * of the action alias (after the `:`) - it will work as long as what you
 * typed only matches a single command. This is similar to typing file and
 * directory names. The short syntax currently does not work for namespaces
 * (the part before `:`).
 * 
 * Any action implementing the `iCanBeCalledFromCLI` interface is
 * automatically made available through the `ConsoleFacade`.
 * 
 * ## Examples
 * 
 * ```
 * vendor/bin/action exface.core:ClearCache
 * vendor/bin/action exface.core:clear
 * vendor/bin/action exface.packagemanager.InstallApp
 * vendor/bin/action exface.packagemanager.install
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class ConsoleFacade extends Application implements FacadeInterface
{
    use AliasTrait;

    private $exface = null;

    private $app = null;

    private $selector = null;

    public final function __construct(FacadeSelectorInterface $selector)
    {
        parent::__construct('ExFace Console');
        $this->exface = $selector->getWorkbench();
        $this->selector = $selector;
        $this->setCommandLoader(new CommandLoader($this));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\FacadeInterface::getSelector()
     */
    public function getSelector() : FacadeSelectorInterface
    {
        return $this->selector;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     * @return Workbench
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\FacadeInterface::is()
     */
    public function is($facade_alias) : bool
    {
        if (strcasecmp($this->getAlias(), $facade_alias) === 0 || strcasecmp($this->getAliasWithNamespace(), $facade_alias) === 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Facades\FacadeInterface::getApp()
     */
    public function getApp() : AppInterface
    {
        if ($this->app === null) {
            $this->app = $this->getWorkbench()->getApp($this->selector->getAppSelector());
        }
        return $this->app;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Facades\FacadeInterface::getConfig()
     */
    public function getConfig() : ConfigurationInterface
    {
        return $this->getApp()->getConfig();
    }
    
    /**
     * Returns a generator, that yields the output of the comand.
     * 
     * @param CliTaskInterface $task
     * @return \Generator
     */
    public function getOutputGenerator(string $cliCommand) : \Generator
    {
        $cliCommand = StringDataType::substringAfter($cliCommand, 'action ', '');
        $input = new StringInput($cliCommand);
        $commandName = $this->getCommandName($input);
        if ($commandName) {
            $command = $this->find($commandName);
        }
        
        // If a real action is called, get it's result with pure PHP.
        // Otherwise leave handling to Symfony Console, which would actually perform the command
        // on the command line. This fallback ensures, that things like"action help" still work!
        if ($command instanceof SymfonyCommandAdapter) {
            $definition = $command->getDefinition();
            // Strange merging-line taken from Syfmony's Application class
            $definition->setArguments(array_merge(
                [
                    'command' => new InputArgument('command', InputArgument::REQUIRED, $command->getDescription()),
                ],
                $definition->getArguments()
                ));
            $input->bind($definition);
            
            $args = $input->getArguments();
            $opts = $input->getOptions();
            
            $task = TaskFactory::createCliTask($this, $command->getAction()->getSelector(), $args, $opts);
            $result = $this->getWorkbench()->handle($task);
            if ($result instanceof ResultMessageStreamInterface) {
                yield from $result->getMessageStreamGenerator();
            } else {
                yield $result->getMessage();
            }
        } else {
            $this->setAutoExit(false);
            $output = new BufferedOutput();
            $this->run($input, $output);
            yield $output->fetch();
        }
    }
}