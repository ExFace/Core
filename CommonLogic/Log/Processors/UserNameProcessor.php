<?php

namespace exface\Core\CommonLogic\Log\Processors;

use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Adds the username to the extra field of the log record.
 * 
 * @author Georg Bieger
 */
class UserNameProcessor extends AbstractContextProcessor
{
    const KEY_USER_NAME = CsvLogConfig::KEY_USER_NAME;

    private WorkbenchInterface $workbench;

    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * @inheritDoc
     */
    protected function invokeInternal(array &$context, array &$extra) : void
    {
        // Make sure the workbench is started.
        if($this->workbench->isStarted()) {
            try {
                // Must not trigger authentication (remember-me, events, recursive logging) from within the logger.
                $value = $this->workbench->getSecurity()->getAuthenticatedTokenIfExists()?->getUsername();
            } catch (\Throwable $e) {
                $value = null;
            }
        } else {
            // If the workbench is not started, getting the username would throw, so we set it to NULL instead.
            $value = null;
        }
        
        $this->setValue(
            $extra,
            self::KEY_USER_NAME,
            $value
        );
    }
}