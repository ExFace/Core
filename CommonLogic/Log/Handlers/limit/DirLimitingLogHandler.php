<?php
namespace exface\Core\CommonLogic\Log\Handlers\limit;

use exface\Core\CommonLogic\Log\Helpers\LogHelper;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LogHandlerInterface;

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
        
        $limitTime = max(0, time() - ($this->maxDays * 24 * 60 * 60));
        $logFiles = glob(LogHelper::getPattern($this->logPath, $this->filenameFormat, '/*', $this->staticFileNamePart));
        
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
}
