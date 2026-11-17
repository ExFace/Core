<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\CommonLogic\Debugger\DebugWidgetRenderer;
use exface\Core\Interfaces\Log\LogEntryInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Monitor;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\LogLevelDataType;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Interfaces\Log\UniqueLogHandlerInterface;
use exface\Core\Exceptions\RuntimeException;

/**
 * Logs entries with the configured log level or above to the exface.Core.MONITOR_ERROR object. 
 * 
 * @author rml
 *
 */
class MonitorLogHandler implements UniqueLogHandlerInterface
{
    private $monitor;
    
    private $debugWidgetRenderer;
    
    private $minLogLevel;
    
    private $workbench;
    
    private $busy = false;
    
    private $failed = false;
    
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
        $this->debugWidgetRenderer = new DebugWidgetRenderer($workbench);
    }
    
    /**
     * Unique per logger: there is only one `exface.Core.MONITOR_ERROR` table, and the handler performs
     * non-idempotent inserts and holds shared state (`busy`/`failed`, the monitor's last row).
     * 
     * Instances that are called directly instead of being appended (e.g. the long-runners handler of the
     * `Monitor`) are not affected.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\UniqueLogHandlerInterface::getUniqueKey()
     */
    public function getUniqueKey() : ?string
    {
        return self::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle(LogEntryInterface $entry) : void
    {
        $level = $entry->getLevel();
        if (LogLevelDataType::compareLogLevels($level, $this->minLogLevel) < 0) {
            return;
        }
        // Prevent recursion if something is wrong with the monitor itself or with the metamodel
        if ($this->busy === true || $this->failed === true) {
            return;
        }
        // Don't try to monitor anything while the workbench is being installed
        if ($this->workbench->isInstalled() === false) {
            return;
        }
        
        $this->busy = true;
        try {
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.MONITOR_ERROR');      
            $ds->addRow([
                'LOG_ID' => $entry->getId(),
                'REQUEST_ID' => $this->workbench->getContext()->getScopeRequest()->getScopeId(),
                'ERROR_LEVEL' => $level,
                'MESSAGE' => $entry->getMessage(),
                'ERROR_WIDGET' => $this->debugWidgetRenderer->render($entry),
                'USER' => $this->workbench->getSecurity()->getAuthenticatedUser()->getUid(),
                'DATE' => DateDataType::now()
            ]);
            $ds->dataCreate();
            $this->monitor->addLogIdToLastRowObject($ds->getUidColumn()->getValue(0));
        } catch (\Throwable $e) {
            $this->failed = true;
            throw new RuntimeException('Failed to log error to monitor. ' . $e->getMessage() . '. Turning off error monitor for this request!', null, $e);
        }
        
        $this->busy = false;
    }
}