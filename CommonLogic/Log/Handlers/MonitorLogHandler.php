<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Factories\DataSheetFactory;
use Monolog\Logger;
use exface\Core\CommonLogic\Monitor;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\LogLevelDataType;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\CommonLogic\Log\Processors\DebugWidgetProcessor;

/**
 * Logs entries with the configured log level or above to the exface.Core.MONITOR_ERROR object. 
 * 
 * @author rml
 *
 */
class MonitorLogHandler implements LogHandlerInterface
{
    private $monitor;
    
    private $debugWidgetProcessor;
    
    private $minLogLevel;
    
    private $workbench;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param Monitor $monitor
     * @param string $level
     */
    function __construct(WorkbenchInterface $workbench, Monitor $monitor, string $level = LoggerInterface::CRITICAL)
    {
        $this->workbench = $workbench;
        $this->monitor = $monitor;
        $this->minLogLevel = $level;
        $this->debugWidgetProcessor = new DebugWidgetProcessor($workbench, 'sender', 'message');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle($level, $message, array $context = [], iCanGenerateDebugWidgets $sender = null)
    {
        if (LogLevelDataType::compareLogLevels($level, $this->minLogLevel) < 0) {
            return;
        }
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.MONITOR_ERROR');      
        $ds->addRow([
            'LOG_ID' => $context["id"],
            'ERROR_LEVEL' => Logger::getLevelName(Logger::toMonologLevel($level)),
            'MESSAGE' => $message,
            'ERROR_WIDGET' => $this->getDebugWidgetJson($sender),
            'USER' => $this->workbench->getSecurity()->getAuthenticatedUser()->getUid(),
            'DATE' => DateDataType::now()
        ]);
        $ds->dataCreate();
        $this->monitor->addLogIdToLastRowObject($ds->getUidColumn()->getValue(0));
        
        return;
    }
    
    /**
     * 
     * @param iCanGenerateDebugWidgets $sender
     * @return string
     */
    protected function getDebugWidgetJson(iCanGenerateDebugWidgets $sender = null) : string
    {
        return call_user_func($this->debugWidgetProcessor, ['context' => ['sender' => $sender]])['message'];
    }
}