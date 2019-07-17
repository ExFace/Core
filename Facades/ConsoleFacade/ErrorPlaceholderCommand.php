<?php
namespace exface\Core\Facades\ConsoleFacade;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ErrorPlaceholderCommand extends Command
{
    private $caughtThrowable;
    private $couldNotLoadDescription;
    
    public function __construct(Throwable $caughtThrowable, string $couldNotLoadDescription, $name = null) {
            $this->caughtThrowable = $caughtThrowable;
            $this->couldNotLoadDescription = $couldNotLoadDescription;
            parent::__construct($name);
    }
    
    protected function configure(): void
    {
        $this->setDescription($this->couldNotLoadDescription . ': ' . $this->caughtThrowable->getMessage());
    }
    
    public function execute(InputInterface $input, OutputInterface $output): void
    {
        throw $this->caughtThrowable;
    }
}