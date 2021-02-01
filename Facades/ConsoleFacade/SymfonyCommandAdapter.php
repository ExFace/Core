<?php
namespace exface\Core\Facades\ConsoleFacade;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\TaskFactory;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use Symfony\Component\Console\Input\InputArgument;
use exface\Core\Facades\ConsoleFacade\Interfaces\FacadeCommandLoaderInterface;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Wraps any action in a native Symfony command.
 * 
 * While this adapter works with any action, it can only read arguments and options
 * from actions implementing the iCanBeCalledFromCLI interface. All other actions
 * can only be called without any arguments or options.
 * 
 * @author Andrej Kabachnik
 *
 */
class SymfonyCommandAdapter extends Command
{
    private $action = null;
    
    private $commandLoader = null;
    
    public function __construct(FacadeCommandLoaderInterface $commandLoader, ActionInterface $action)
    {
        $this->action = $action;
        $this->commandLoader = $commandLoader;
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setName($this->commandLoader->getCommandNameFromAlias($this->action->getAliasWithNamespace()))
            // the short description shown while running "php bin/console list"
            ->setDescription($this->action->getName())
            
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('')
        ;
        
        // IDEA implement default arguments for page, object and widget selectors and input/prefill data?
        
        if ($this->action instanceof iCanBeCalledFromCLI) {
            foreach ($this->action->getCliArguments() as $param) {
                $mode = null;
                if ($param->isRequired()) {
                    $mode = InputArgument::REQUIRED;
                }
                $this->addArgument($param->getName(), $mode, $param->getDescription(), $param->getDefaultValue());
            }
            foreach ($this->action->getCliOptions() as $param) {
                /* @var $param \exface\Core\Interfaces\Actions\ServiceParameterInterface */
                if ($param->hasDefaultValue() === true) {
                    $mode = InputOption::VALUE_OPTIONAL;
                } else {
                    $mode = InputOption::VALUE_REQUIRED;
                }
                $this->addOption($param->getName(), null, $mode, $param->getDescription(), $param->getDefaultValue());
            }
        }
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $task = TaskFactory::createCliTask($this->commandLoader->getFacade(), $this->action->getSelector(), $input->getArguments(), $input->getOptions());
        $result = $this->getWorkbench()->handle($task);
        if ($result instanceof ResultMessageStreamInterface) {
            foreach ($result->getMessageStreamGenerator() as $msg) {
                $output->write($msg);
            }
        } else {
            $output->writeln($result->getMessage());
        }
        return Command::SUCCESS;
    }
    
    public function getWorkbench()
    {
        return $this->action->getWorkbench();
    }
    
    /**
     * 
     * @return ActionInterface
     */
    public function getAction() : ActionInterface
    {
        return $this->action;
    }
}