<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\CommonLogic\Workbench;

class LogMonitorHandler implements LogHandlerInterface
{
    private $workbench;
    
    private $level;
    
    function __construct(WorkbenchInterface $workbench, $level = LoggerInterface::DEBUG)
    {
        $this->workbench = $workbench;
        $this->level = $level;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Log\Handlers\monolog\AbstractMonologHandler::handle()
     */
    public function handle($level, $message, array $context = [], iCanGenerateDebugWidgets $sender = null)
    {
        
    }
    
    protected function createRealLogger()
    {}

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

}