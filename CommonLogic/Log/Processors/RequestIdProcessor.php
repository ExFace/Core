<?php

namespace exface\Core\CommonLogic\Log\Processors;


use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Adds the requestId to the extra field of the log record.
 * 
 * @author Georg Bieger
 */
class RequestIdProcessor extends AbstractContextProcessor
{
    const KEY_REQUEST_ID = CsvLogConfig::KEY_REQUEST_ID;

    private WorkbenchInterface $workbench;

    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * @inheritDoc
     */
    protected function invokeInternal(array &$context, array &$extra): void
    {
        // Make sure the workbench is started.
        if($this->workbench->isStarted()) {
            try {
                $value = $this->workbench->getContext()->getScopeRequest()->getScopeId();
            } catch (\Throwable $e) {
                $value = null;
            }
        } else {
            // If the workbench is not started, getting the request ID would throw, so we set it to NULL instead.
            $value = null;
        }
        
        $this->setValue(
            $extra,
            self::KEY_REQUEST_ID,
            $value
        );
    }
}