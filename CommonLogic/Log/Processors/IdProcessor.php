<?php
namespace exface\Core\CommonLogic\Log\Processors;

use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\Interfaces\Log\LogEntryInterface;

/**
 * Copies the ID of the log entry in the record context to the extra data (`CsvLogConfig::KEY_ID`).
 * 
 * The entry itself stays in the context - remove it with a `ContextFilterProcessor` where needed.
 * 
 * @author Georg Bieger
 */
class IdProcessor extends AbstractContextProcessor
{
    private string $logEntryContextKey;
    
    /**
     * @param string $logEntryContextKey context key of the `LogEntryInterface` in the Monolog record
     */
    public function __construct(string $logEntryContextKey)
    {
        $this->logEntryContextKey = $logEntryContextKey;
    }
    
    /**
     * @inheritDoc
     */
    protected function invokeInternal(array &$context, array &$extra): void
    {
        $entry = $context[$this->logEntryContextKey] ?? null;
        if ($entry instanceof LogEntryInterface) {
            $this->setValue($extra, CsvLogConfig::KEY_ID, $entry->getId());
        }
    }
}