<?php
namespace exface\Core\CommonLogic\Log\Handlers\limit;

use exface\Core\CommonLogic\Log\Helpers\LogHelper;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Interfaces\AppInterface;

/**
 * Log handler that uses the given createCallback to instantiate an underlying log handler that logs files with a
 * specific file name schema to a specific directory and limits the number of days to keep the log files from to the
 * given value of maxDays.
 *
 * @package exface\Core\CommonLogic\Log\Handlers
 */
class DirLimitingLogHandler extends LimitingWrapper
{

    private $logPath;

    private $staticFileNamePart;

    private $maxDays;

    private $filenameFormat;

    function __construct(LogHandlerInterface $handler, $logPath, $staticFileNamePart, $maxDays = 0)
    {
        $this->logPath = $logPath;
        $this->staticFileNamePart = $staticFileNamePart;
        $this->maxDays = $maxDays;
        $this->filenameFormat = '{filename}{variable}{static}';
        
        parent::__construct($handler);
    }

    protected function callLogger(LogHandlerInterface $handler, $level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        $handler->handle($level, $message, $context, $sender);
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
        
        // Get the time of the last cleanup. There is no need to perform the check
        // more than once a day as the lifetime of the logs is defined in days.
        $config = $this->getWorkbench()->getConfig();
        try {
            $last_cleanup = $config->getOption('LOG.LAST_CLEANUP');
        } catch (ConfigOptionNotFoundError $e){
            // If there was no last cleanup value yet, just set to now and skip the rest
            $config->setOption('LOG.LAST_CLEANUP', date("Y-m-d H:i:s"), AppInterface::CONFIG_SCOPE_SYSTEM);
            return;
        }
        
        // If the last cleanup took place less then a day ago, skip the rest.
        if (strtotime($last_cleanup) > (time()-(60*60*24))){
            return;
        }
        
        $limitTime = max(0, time() - ($this->maxDays * 24 * 60 * 60));
        $logFiles = glob(LogHelper::getPattern($this->logPath, $this->filenameFormat, '/*', $this->staticFileNamePart));
        foreach ($logFiles as $logFile) {
            $mtime = filemtime($logFile);
            if ($mtime > $limitTime)
                continue;
            
            if (is_writable($logFile)) {
                // suppress errors here as unlink() might fail if two processes
                // are cleaning up/rotating at the same time
                set_error_handler(function ($errno, $errstr, $errfile, $errline) {});
                unlink($logFile);
                restore_error_handler();
            }
        }
        
        return;
    }
}
