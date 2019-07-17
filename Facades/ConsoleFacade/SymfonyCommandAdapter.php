<?php
namespace exface\Core\Facades\ConsoleFacade;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\TaskFactory;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use Symfony\Component\Console\Input\InputArgument;

class SymfonyCommandAdapter extends Command
{
    private $action = null;
    
    public function __construct(ActionInterface $action)
    {
        $this->action = $action;
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setName(ConsoleFacade::convertAliasToCommandName($this->action->getAliasWithNamespace()))
            // the short description shown while running "php bin/console list"
            ->setDescription($this->action->getName())
            
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('')
        ;
        
        if ($this->action instanceof iCanBeCalledFromCLI) {
            foreach ($this->action->getCliArguments() as $param) {
                $mode = null;
                if ($param->isRequired()) {
                    $mode = InputArgument::REQUIRED;
                }
                $this->addArgument($param->getName(), $mode, $param->getDescription(), $param->getDefaultValue());
            }
            foreach ($this->action->getCliOptions() as $param) {
                $this->addOption($param->getName(), null, null, $param->getDescription(), $param->getDefaultValue());
            }
        }
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $task = TaskFactory::createEmpty($this->action->getWorkbench());
        $result = $this->action->handle($task);
        $output->writeln($result->getMessage());
    }
}