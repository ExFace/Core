<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Events\Workbench\OnBeforeStopEvent;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Handler wrapper to clean up the given directory removing all files older than X days.
 *
 * @package exface\Core\CommonLogic\Log\Handlers\rotation
 */
class LimitingHandler implements LogHandlerInterface
{

    /** @var LogHandlerInterface $handler */
    private $handler;
    
    private $limitPerformed = false;
    
    private $maxDays;
    
    private $logPath;
    
    private $fileExtension;
    
    private $workbench;

    /**
     * 
     * @param LogHandlerInterface $handler
     */
    public function __construct(LogHandlerInterface $handler, WorkbenchInterface $workbench, string $dirPathAbsolute, int $maxDays = 0, string $fileExtension = '.log')
    {
        $this->handler = $handler;
        $this->maxDays = $maxDays;
        $this->logPath = $dirPathAbsolute;
        $this->fileExtension = $fileExtension;
        $this->workbench = $workbench;
    }
    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        $this->handler->handle($level, $message, $context, $sender);
        if ($this->limitPerformed === true || ! $this->workbench->isStarted()) {
            return;
        }
        
        $this->limitPerformed = true;
        
        // Get the time of the last cleanup. There is no need to perform the check
        // more than once a day as the lifetime of the logs is defined in days.
        $ctxtScope = $this->workbench->getContext()->getScopeInstallation();
        $last_cleanup = $ctxtScope->getVariable('last_log_cleanup');
        if (! $last_cleanup) {
            // If there was no last cleanup value yet, just set to now and skip the rest
            $ctxtScope->setVariable('last_log_cleanup', date("Y-m-d H:i:s"));
            return;
        }
        
        // If the last cleanup took place less then a day ago, skip the rest.
        if (strtotime($last_cleanup) > (time()-(60*60*24))){
            return;
        }
        
        $this->limit();
        
        $this->workbench->eventManager()->addListener(OnBeforeStopEvent::getEventName(), [$this, 'handleOnBeforeStopEvent']);
    }
    
    
    
    /**
     * Log file cleanup.
     */
    protected function limit()
    {
        // skip GC of old logs if files are unlimited
        if (0 === $this->maxDays) {
            return;
        }
        
        $limitTime = max(0, time() - ($this->maxDays * 24 * 60 * 60));
        $logFiles = glob($this->logPath . '/*.' . $this->fileExtension);
        
        // suppress errors here as unlink() might fail if two processes
        // are cleaning up/rotating at the same time
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {});
        foreach ($logFiles as $logFile) {
            if (is_writable($logFile)) {
                $mtime = filemtime($logFile);
                if ($mtime > $limitTime) {
                    continue;
                }
                @unlink($logFile);
            }
        }
        restore_error_handler();
        
        return;
    }
    
    /**
     * 
     * @param OnBeforeStopEvent $event
     */
    public function handleOnBeforeStopEvent(OnBeforeStopEvent $event)
    {
        $this->workbench->getContext()->getScopeInstallation()->setVariable('last_log_cleanup', date("Y-m-d H:i:s"));
        return;
    }
}