<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\Log\Helpers\LogHelper;
use Monolog\Logger;
use exface\Core\CommonLogic\Monitor;
use exface\Core\DataTypes\DateDataType;

/**
 * The monitor logs log entries with the configured error level or above to the MONITOR_ERROR object. 
 * 
 * @author rml
 *
 */
class LogMonitorHandler extends DebugMessageFileHandler
{
    private $logIds = [];
    
    private $monitor;
    
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
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Log\Handlers\monolog\AbstractMonologHandler::handle()
     */
    public function handle($level, $message, array $context = [], iCanGenerateDebugWidgets $sender = null)
    {
        if (LogHelper::compareLogLevels($level, $this->minLogLevel) < 0) {
            return;
        }
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.MONITOR_ERROR');
        if ($sender) {
            try {
                $debugWidget     = $sender->createDebugWidget($this->createDebugMessage());
                $debugWidgetData = $debugWidget->exportUxonObject()->toJson(true);
            } catch (\Throwable $e){
                // If errors occur when creating debug widgets, just create an
                // HTML-widget with an exception-dump. If the sender was an error,
                // dump it, otherwise dump the error, that just occured.
                if ($sender instanceof ExceptionInterface){
                    $exception = $sender;
                } else {
                    $exception = $e;
                }
                $debugWidgetData = $this->createHtmlFallback($this->getWorkbench()->getDebugger()->printException($exception, true));
            }
        } elseif ($context['exception'] instanceof \Throwable){
            // If there is no sender, but the context contains an error, use
            // the error fallback to create a debug widget
            $debugWidgetData = $this->createHtmlFallback($this->getWorkbench()->getDebugger()->printException($context['exception'], true));
        } else {
            // If all the above fails, simply dump the context to the debug widget
            $debugWidgetData = $this->createHtmlFallback($this->getWorkbench()->getDebugger()->printVariable($context, true));
        }
        
        $ds->addRow([
            'LOG_ID' => $context["id"],
            'ERROR_LEVEL' => Logger::getLevelName(Logger::toMonologLevel($level)),
            'MESSAGE' => $message,
            'ERROR_WIDGET' => $debugWidgetData,
            'USER' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
            'DATE' => DateDataType::now(),
            'STATUS' => '20'
        ]);
        $ds->dataCreate();
        $this->monitor->addLogIdToLastRowObject($ds->getUidColumn()->getValue(0));
        
        return;
    }
}